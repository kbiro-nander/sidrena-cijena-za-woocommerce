<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena;

use SidrenaCijena\Admin\AdminActions;
use SidrenaCijena\Admin\ProductFields;
use SidrenaCijena\Admin\ProductSave;
use SidrenaCijena\Admin\SettingsPage;
use SidrenaCijena\Admin\StatusProvider;
use SidrenaCijena\Admin\Tools\CsvExporter;
use SidrenaCijena\Admin\Tools\CsvImporter;
use SidrenaCijena\Admin\Tools\SnapshotRequest;
use SidrenaCijena\Admin\Tools\SnapshotService;
use SidrenaCijena\Admin\Tools\ToolsAjax;
use SidrenaCijena\Admin\Tools\ToolsPage;
use SidrenaCijena\Admin\VariationFields;
use SidrenaCijena\Cli\Commands;
use SidrenaCijena\Display\Assets;
use SidrenaCijena\Display\BadgeDataFactory;
use SidrenaCijena\Display\CartFilters;
use SidrenaCijena\Display\PriceBadge;
use SidrenaCijena\Display\PriceFormatter;
use SidrenaCijena\Display\PriceHtmlComposer;
use SidrenaCijena\Display\PriceHtmlFilter;
use SidrenaCijena\Display\RenderGuard;
use SidrenaCijena\Display\RequestContext;
use SidrenaCijena\Display\Shortcode;
use SidrenaCijena\Display\VariationJsonFilter;
use SidrenaCijena\Endpoint\Endpoint;
use SidrenaCijena\Endpoint\Headers;
use SidrenaCijena\Endpoint\IndexRenderer;
use SidrenaCijena\History\DailySweep;
use SidrenaCijena\History\LowestPriceCalculator;
use SidrenaCijena\History\OmnibusStateUpdater;
use SidrenaCijena\History\PriceChangeListener;
use SidrenaCijena\History\PriceHistoryRepository;
use SidrenaCijena\History\PriceRecord;
use SidrenaCijena\History\Pruner;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Schema;
use SidrenaCijena\Lifecycle\Requirements;
use SidrenaCijena\Lifecycle\Upgrader;
use SidrenaCijena\Notices\AdminNotices;
use SidrenaCijena\Notices\Environment;
use SidrenaCijena\PriceList\Collector;
use SidrenaCijena\PriceList\CsvWriter;
use SidrenaCijena\PriceList\FilenameBuilder;
use SidrenaCijena\PriceList\Generator;
use SidrenaCijena\PriceList\ItemFactory;
use SidrenaCijena\PriceList\Manifest;
use SidrenaCijena\PriceList\Outlets;
use SidrenaCijena\PriceList\Retention;
use SidrenaCijena\PriceList\Storage;
use SidrenaCijena\PriceList\XmlWriter;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\MissingReferenceCounter;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\JobRunner;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Scheduling\SchedulerBackend;
use SidrenaCijena\Scheduling\ServiceChangeDebouncer;
use SidrenaCijena\Scheduling\WpCronBackend;
use SidrenaCijena\Settings\Sanitizer;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\StoreApi\ExtendStoreApi;
use SidrenaCijena\Support\Clock;
use SidrenaCijena\Support\WpClock;
use WC_Product;

final class Plugin {

	public const TEXT_DOMAIN     = 'sidrena-cijena-za-woocommerce';
	public const SWEEP_PAGE_SIZE = 300;

	private static ?Plugin $instance = null;
	private Container $container;
	private bool $booted = false;

	public function __construct( ?Container $container = null ) {
		$this->container = $container ?? new Container();
		$this->wire();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function setInstance( ?Plugin $plugin ): void {
		self::$instance = $plugin;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $id Service class name.
	 * @return T
	 */
	public function get( string $id ) {
		/** @var T $service */
		$service = $this->container->get( $id );
		return $service;
	}

	public function container(): Container {
		return $this->container;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$problems = Requirements::check();
		if ( [] !== $problems ) {
			add_action(
				'admin_notices',
				static function () use ( $problems ): void {
					foreach ( $problems as $problem ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $problem ) . '</p></div>';
					}
				}
			);
			return;
		}

		add_action( 'init', [ $this, 'loadTextDomain' ] );
		add_action( 'init', [ Upgrader::class, 'maybeUpgrade' ], 5 );

		// Front-end display.
		$this->get( PriceHtmlFilter::class )->register();
		$this->get( VariationJsonFilter::class )->register();
		$this->get( CartFilters::class )->register();
		$this->get( Shortcode::class )->register();
		$this->get( Assets::class )->register();
		$this->get( ExtendStoreApi::class )->register();

		// History / Omnibus.
		if ( (bool) $this->get( Settings::class )->get( 'history.enabled', true ) ) {
			$this->get( PriceChangeListener::class )->register();
			$forget = function ( $productId ): void {
				$this->get( PriceHistoryRepository::class )->deleteFor( (int) $productId );
			};
			add_action( 'woocommerce_delete_product', $forget );
			add_action( 'woocommerce_delete_product_variation', $forget );
		}

		// Price list endpoint + scheduling.
		$this->get( Endpoint::class )->register();
		$this->get( ServiceChangeDebouncer::class )->register();
		$this->get( JobRunner::class )->register();
		$this->get( Scheduler::class )->registerWatchdog();

		$flush = function (): void {
			$this->get( MissingReferenceCounter::class )->flush( $this->get( ReferencePriceRegistry::class )->primary() );
		};
		add_action( 'scwc_snapshot_completed', $flush );
		add_action( 'scwc_import_completed', $flush );

		if ( is_admin() ) {
			$this->get( SettingsPage::class )->register();
			$this->get( ProductFields::class )->register();
			$this->get( VariationFields::class )->register();
			$this->get( ProductSave::class )->register();
			$this->get( ToolsPage::class )->register();
			$this->get( ToolsAjax::class )->register();
			$this->get( AdminActions::class )->register();
			$this->get( AdminNotices::class )->register();
		}

		if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			Commands::registerCommand( $this->get( Commands::class ) );
		}

		do_action( 'scwc_booted', $this );
	}

	/** Called from the activation hook (plugins_loaded has already fired in that request). */
	public function onActivate(): void {
		$this->boot();
		$scheduler = $this->get( Scheduler::class );
		$scheduler->ensureScheduled();
		// Seed price history for every product in the background.
		$scheduler->enqueue( Scheduler::HOOK_SWEEP_PAGE, [ 'page' => 1 ] );
	}

	public function onDeactivate(): void {
		$this->boot();
		$this->get( Scheduler::class )->unscheduleAll();
	}

	public function loadTextDomain(): void {
		load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( SCWC_PLUGIN_FILE ) ) . '/languages' );
	}

	private function wire(): void {
		$c = $this->container;

		$c->set( Settings::class, static fn() => Settings::fromOption() );
		$c->set( Clock::class, static fn() => new WpClock() );
		$c->set( ReferencePriceRegistry::class, static fn( Container $c ) => ReferencePriceRegistry::fromSettings( $c->get( Settings::class ) ) );
		$c->set( BrandResolver::class, static fn() => new BrandResolver() );
		$c->set( BarcodeResolver::class, static fn() => new BarcodeResolver() );
		$c->set( ProductAdapter::class, static fn( Container $c ) => new ProductAdapter( $c->get( BrandResolver::class ), $c->get( BarcodeResolver::class ) ) );
		$c->set( ServiceRule::class, static fn( Container $c ) => new ServiceRule( (string) $c->get( Settings::class )->get( 'price_list.service_rule', 'virtual_or_flag' ) ) );
		$c->set( CategoryOverrideResolver::class, static fn() => CategoryOverrideResolver::forWordPress() );
		$c->set( ReferenceDateResolver::class, static fn( Container $c ) => new ReferenceDateResolver( $c->get( CategoryOverrideResolver::class ) ) );
		$c->set( ReferencePriceRepository::class, static fn( Container $c ) => new ReferencePriceRepository( $c->get( ReferenceDateResolver::class ) ) );
		$c->set(
			MissingReferenceCounter::class,
			static function (): MissingReferenceCounter {
				global $wpdb;
				return new MissingReferenceCounter( $wpdb );
			}
		);

		$c->set(
			'product_loader',
			static fn() => static function ( int $id ): ?WC_Product {
			$product = wc_get_product( $id );
			return $product instanceof WC_Product ? $product : null;
			}
		);
		$c->set( 'request_context', static fn() => static fn(): RequestContext => RequestContext::fromGlobals() );

		// Display.
		$c->set( PriceFormatter::class, static fn() => new PriceFormatter() );
		$c->set( RenderGuard::class, static fn( Container $c ) => new RenderGuard( $c->get( Settings::class ), $c->get( 'request_context' ) ) );
		$c->set(
			BadgeDataFactory::class,
			static fn( Container $c ) => new BadgeDataFactory(
				$c->get( Settings::class ),
				$c->get( ReferencePriceRegistry::class ),
				$c->get( ProductAdapter::class ),
				$c->get( ReferencePriceRepository::class ),
				$c->get( PriceFormatter::class ),
				$c->get( 'product_loader' ),
			)
		);
		$c->set( PriceBadge::class, static fn( Container $c ) => new PriceBadge( $c->get( Settings::class ), SCWC_PLUGIN_DIR . 'templates' ) );
		$c->set( PriceHtmlComposer::class, static fn( Container $c ) => new PriceHtmlComposer( $c->get( Settings::class ), $c->get( PriceBadge::class ) ) );
		$c->set( PriceHtmlFilter::class, static fn( Container $c ) => new PriceHtmlFilter( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceHtmlComposer::class ) ) );
		$c->set( VariationJsonFilter::class, static fn( Container $c ) => new VariationJsonFilter( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ) ) );
		$c->set( CartFilters::class, static fn( Container $c ) => new CartFilters( $c->get( Settings::class ), $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceHtmlComposer::class ), $c->get( 'request_context' ) ) );
		$c->set( Shortcode::class, static fn( Container $c ) => new Shortcode( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceBadge::class ), $c->get( 'product_loader' ) ) );
		$c->set( Assets::class, static fn( Container $c ) => new Assets( $c->get( Settings::class ) ) );
		$c->set( ExtendStoreApi::class, static fn( Container $c ) => new ExtendStoreApi( $c->get( BadgeDataFactory::class ), $c->get( PriceBadge::class ) ) );

		// History / Omnibus.
		$c->set(
			PriceHistoryRepository::class,
			static function (): PriceHistoryRepository {
				global $wpdb;
				return new PriceHistoryRepository( $wpdb, Schema::tableName( $wpdb ) );
			}
		);
		$c->set( Recorder::class, static fn( Container $c ) => new Recorder( $c->get( PriceHistoryRepository::class ), $c->get( Clock::class ) ) );
		$c->set( LowestPriceCalculator::class, static fn( Container $c ) => new LowestPriceCalculator( $c->get( PriceHistoryRepository::class ) ) );
		$c->set( OmnibusStateUpdater::class, static fn( Container $c ) => new OmnibusStateUpdater( $c->get( LowestPriceCalculator::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );
		$c->set( PriceChangeListener::class, static fn( Container $c ) => new PriceChangeListener( $c->get( ProductAdapter::class ), $c->get( Recorder::class ), $c->get( OmnibusStateUpdater::class ), $c->get( 'product_loader' ) ) );
		$c->set( DailySweep::class, static fn( Container $c ) => new DailySweep( DailySweep::wcPager(), $c->get( 'product_loader' ), $c->get( ProductAdapter::class ), $c->get( Recorder::class ), $c->get( OmnibusStateUpdater::class ) ) );
		$c->set( Pruner::class, static fn( Container $c ) => new Pruner( $c->get( PriceHistoryRepository::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );

		// Price list.
		$c->set( ItemFactory::class, static fn( Container $c ) => new ItemFactory( $c->get( Settings::class ), $c->get( ReferencePriceRegistry::class ), $c->get( ReferencePriceRepository::class ), $c->get( ServiceRule::class ) ) );
		$c->set(
			Collector::class,
			static function ( Container $c ): Collector {
				$observer = null;
				if ( (bool) $c->get( Settings::class )->get( 'history.enabled', true ) ) {
					$recorder = $c->get( Recorder::class );
					$updater  = $c->get( OmnibusStateUpdater::class );
					$observer = static function ( ProductSnapshot $snapshot ) use ( $recorder, $updater ): void {
						$updater->apply( $snapshot, $recorder->record( $snapshot, 'sweep' ) );
					};
				}
				return new Collector( Collector::wcPager(), $c->get( 'product_loader' ), $c->get( ProductAdapter::class ), $c->get( ItemFactory::class ), 200, $observer );
			}
		);
		$c->set( Storage::class, static fn() => Storage::fromUploads() );
		$c->set( Manifest::class, static fn( Container $c ) => new Manifest( $c->get( Storage::class ) ) );
		$c->set( Retention::class, static fn( Container $c ) => new Retention( $c->get( Manifest::class ), $c->get( Storage::class ), $c->get( Clock::class ) ) );
		$c->set(
			Generator::class,
			static function ( Container $c ): Generator {
				$settings = $c->get( Settings::class );
				return new Generator(
					$settings,
					static fn(): array => Outlets::fromSettings( $settings ),
					$c->get( Collector::class ),
					[
						'xml' => new XmlWriter(),
						'csv' => new CsvWriter( (string) $settings->get( 'price_list.csv_delimiter', ';' ), (bool) $settings->get( 'price_list.csv_bom', true ) ),
					],
					new FilenameBuilder(),
					$c->get( Storage::class ),
					$c->get( Manifest::class ),
					$c->get( Retention::class ),
					$c->get( Clock::class ),
					$c->get( ReferencePriceRegistry::class ),
				);
			}
		);
		$c->set( IndexRenderer::class, static fn( Container $c ) => new IndexRenderer( $c->get( Settings::class ), $c->get( Storage::class ), $c->get( Manifest::class ), SCWC_PLUGIN_DIR . 'templates' ) );
		$c->set(
			Endpoint::class,
			static fn( Container $c ) => new Endpoint(
				$c->get( Settings::class ),
				$c->get( Storage::class ),
				$c->get( Manifest::class ),
				new Headers(),
				$c->get( IndexRenderer::class ),
				static fn( string $reason ): bool => $c->get( Generator::class )->run( $reason )->ok(),
			)
		);

		// Scheduling.
		$c->set( SchedulerBackend::class, static fn() => ActionSchedulerBackend::available() ? new ActionSchedulerBackend() : new WpCronBackend() );
		$c->set( Scheduler::class, static fn( Container $c ) => new Scheduler( $c->get( SchedulerBackend::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );
		$c->set( ServiceChangeDebouncer::class, static fn( Container $c ) => new ServiceChangeDebouncer( $c->get( Scheduler::class ), $c->get( Settings::class ), $c->get( ServiceRule::class ) ) );
		$c->set(
			JobRunner::class,
			static fn( Container $c ) => new JobRunner(
				$c->get( Scheduler::class ),
				static function ( string $reason ) use ( $c ): void {
				$c->get( Generator::class )->run( $reason );
				},
				static fn( int $page ) => $c->get( DailySweep::class )->run( $page, self::SWEEP_PAGE_SIZE ),
				static function () use ( $c ): void {
				$c->get( Pruner::class )->run();
				},
				static function ( string $typeKey ) use ( $c ): void {
				$request = SnapshotRequest::fromArray(
					[
						'type' => $typeKey,
						'mode' => 'only_missing',
					],
					$c->get( Settings::class )
				);
				$page    = 1;
				do {
					$result = $c->get( SnapshotService::class )->run( $request, $page, 200 );
					++$page;
				} while ( $result->hasMore );
				do_action( 'scwc_snapshot_completed', $request );
				},
			)
		);

		// Admin.
		$c->set( SnapshotService::class, static fn( Container $c ) => new SnapshotService( $c->get( ReferencePriceRegistry::class ), $c->get( ReferencePriceRepository::class ), $c->get( ProductAdapter::class ), $c->get( Recorder::class ), DailySweep::wcPager(), $c->get( 'product_loader' ) ) );
		$c->set( CsvImporter::class, static fn( Container $c ) => new CsvImporter( $c->get( ReferencePriceRegistry::class ), $c->get( ReferencePriceRepository::class ) ) );
		$c->set( CsvExporter::class, static fn( Container $c ) => new CsvExporter( $c->get( ReferencePriceRegistry::class ), $c->get( ReferencePriceRepository::class ), $c->get( ProductAdapter::class ), DailySweep::wcPager(), $c->get( 'product_loader' ) ) );
		$c->set( ToolsAjax::class, static fn( Container $c ) => new ToolsAjax( $c->get( SnapshotService::class ), $c->get( CsvImporter::class ), $c->get( CsvExporter::class ), $c->get( Settings::class ) ) );
		$c->set( ToolsPage::class, static fn( Container $c ) => new ToolsPage( $c->get( Settings::class ), $c->get( ReferencePriceRegistry::class ), SCWC_PLUGIN_DIR . 'templates' ) );
		$c->set( StatusProvider::class, static fn( Container $c ) => new StatusProvider( $c->get( Settings::class ), $c->get( Scheduler::class ), $c->get( Clock::class ) ) );
		$c->set( SettingsPage::class, static fn( Container $c ) => new SettingsPage( $c->get( Settings::class ), $c->get( ReferencePriceRegistry::class ), new Sanitizer(), $c->get( PriceBadge::class ), $c->get( StatusProvider::class ), null, SCWC_PLUGIN_DIR . 'templates' ) );
		$c->set( ProductFields::class, static fn( Container $c ) => new ProductFields( $c->get( ReferencePriceRegistry::class ), $c->get( Settings::class ) ) );
		$c->set( VariationFields::class, static fn( Container $c ) => new VariationFields( $c->get( ReferencePriceRegistry::class ) ) );
		$c->set( ProductSave::class, static fn( Container $c ) => new ProductSave( $c->get( ReferencePriceRegistry::class ) ) );
		$c->set(
			AdminActions::class,
			static fn( Container $c ) => new AdminActions(
				static fn(): ?string => $c->get( Generator::class )->run( 'manual' )->error,
				static function () use ( $c ): void {
				$c->get( JobRunner::class )->sweep();
				},
				static function () use ( $c ): void {
				$c->get( Scheduler::class )->reschedule();
				},
			)
		);
		$c->set(
			AdminNotices::class,
			static fn( Container $c ) => new AdminNotices(
				$c->get( Settings::class ),
				static function () use ( $c ): Environment {
				$last   = get_option( Generator::OPTION_LAST, [] );
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				$cartId = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'cart' ) : 0;
				return new Environment(
					$c->get( Scheduler::class )->status()[ Scheduler::HOOK_GENERATE ] ?? null,
					is_array( $last ) && [] !== $last ? $last : null,
					$c->get( MissingReferenceCounter::class )->cached( $c->get( ReferencePriceRegistry::class )->primary() ),
					$cartId > 0 && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cartId ),
					'' !== (string) get_option( 'permalink_structure', '' ),
					defined( 'WC_VERSION' ) ? (string) constant( 'WC_VERSION' ) : '0',
					$screen instanceof \WP_Screen ? (string) $screen->id : '',
					AdminNotices::dismissedFor( get_current_user_id() ),
				);
				},
				$c->get( Clock::class ),
			)
		);

		// CLI.
		$c->set(
			Commands::class,
			static fn( Container $c ) => new Commands(
				$c->get( Settings::class ),
				static fn( string $reason ) => $c->get( Generator::class )->run( $reason ),
				static fn( SnapshotRequest $request, int $page ) => $c->get( SnapshotService::class )->run( $request, $page, 200 ),
				static function ( string $csv, string $type, bool $dryRun ) use ( $c ): array {
				$importer = $c->get( CsvImporter::class );
				$preview  = $importer->parse( $csv, $type );
				if ( $dryRun ) {
					return [
						'valid'   => $preview->valid,
						'invalid' => $preview->invalid,
						'errors'  => $preview->errors(),
					];
				}
				$result = $importer->apply( $preview );
				do_action( 'scwc_import_completed', $result );
				return [
					'updated'  => $result->updated,
					'markedNa' => $result->markedNa,
					'errors'   => $result->errors,
				];
				},
				static fn( int $page ) => $c->get( DailySweep::class )->run( $page, self::SWEEP_PAGE_SIZE ),
				static function () use ( $c ): void {
				$c->get( Pruner::class )->run();
				},
				static fn(): array => $c->get( Scheduler::class )->status(),
				static function () use ( $c ): void {
				$c->get( Scheduler::class )->reschedule();
				},
				static fn( int $id, int $limit ): array => array_map(
					static fn( PriceRecord $r ): array => [
						'recorded_at' => $r->recordedAt->format( 'Y-m-d H:i:s' ),
						'regular'     => $r->regular,
						'sale'        => $r->sale,
						'active'      => $r->active,
						'on_sale'     => $r->isOnSale ? 'da' : 'ne',
						'source'      => $r->source,
					],
					$c->get( PriceHistoryRepository::class )->historyFor( $id, $limit )
				),
			)
		);
	}
}
