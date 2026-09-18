<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena;

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
use SidrenaCijena\History\DailySweep;
use SidrenaCijena\History\LowestPriceCalculator;
use SidrenaCijena\History\OmnibusStateUpdater;
use SidrenaCijena\History\PriceChangeListener;
use SidrenaCijena\History\PriceHistoryRepository;
use SidrenaCijena\History\Pruner;
use SidrenaCijena\History\Recorder;
use SidrenaCijena\History\Schema;
use SidrenaCijena\Lifecycle\Requirements;
use SidrenaCijena\Lifecycle\Upgrader;
use SidrenaCijena\Product\BarcodeResolver;
use SidrenaCijena\Product\BrandResolver;
use SidrenaCijena\Product\ProductAdapter;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Reference\CategoryOverrideResolver;
use SidrenaCijena\Reference\ReferenceDateResolver;
use SidrenaCijena\Reference\ReferencePriceRegistry;
use SidrenaCijena\Reference\ReferencePriceRepository;
use SidrenaCijena\Scheduling\ActionSchedulerBackend;
use SidrenaCijena\Scheduling\Scheduler;
use SidrenaCijena\Scheduling\SchedulerBackend;
use SidrenaCijena\Scheduling\ServiceChangeDebouncer;
use SidrenaCijena\Scheduling\WpCronBackend;
use SidrenaCijena\Settings\Settings;
use SidrenaCijena\StoreApi\ExtendStoreApi;
use SidrenaCijena\Support\Clock;
use SidrenaCijena\Support\WpClock;
use WC_Product;

final class Plugin {

	public const TEXT_DOMAIN = 'sidrena-cijena-za-woocommerce';

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

		$this->get( PriceHtmlFilter::class )->register();
		$this->get( VariationJsonFilter::class )->register();
		$this->get( CartFilters::class )->register();
		$this->get( Shortcode::class )->register();
		$this->get( Assets::class )->register();
		$this->get( ExtendStoreApi::class )->register();

		if ( (bool) $this->get( Settings::class )->get( 'history.enabled', true ) ) {
			$this->get( PriceChangeListener::class )->register();
		}
		$this->get( ServiceChangeDebouncer::class )->register();
		$this->get( Scheduler::class )->registerWatchdog();

		do_action( 'scwc_booted', $this );
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

		$c->set( 'product_loader', static fn() => static function ( int $id ): ?WC_Product {
			$product = wc_get_product( $id );
			return $product instanceof WC_Product ? $product : null;
		} );
		$c->set( 'request_context', static fn() => static fn(): RequestContext => RequestContext::fromGlobals() );

		$c->set( PriceFormatter::class, static fn() => new PriceFormatter() );
		$c->set( RenderGuard::class, static fn( Container $c ) => new RenderGuard( $c->get( Settings::class ), $c->get( 'request_context' ) ) );
		$c->set( BadgeDataFactory::class, static fn( Container $c ) => new BadgeDataFactory(
			$c->get( Settings::class ),
			$c->get( ReferencePriceRegistry::class ),
			$c->get( ProductAdapter::class ),
			$c->get( ReferencePriceRepository::class ),
			$c->get( PriceFormatter::class ),
			$c->get( 'product_loader' ),
		) );
		$c->set( PriceBadge::class, static fn( Container $c ) => new PriceBadge( $c->get( Settings::class ), SCWC_PLUGIN_DIR . 'templates' ) );
		$c->set( PriceHtmlComposer::class, static fn( Container $c ) => new PriceHtmlComposer( $c->get( Settings::class ), $c->get( PriceBadge::class ) ) );
		$c->set( PriceHtmlFilter::class, static fn( Container $c ) => new PriceHtmlFilter( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceHtmlComposer::class ) ) );
		$c->set( VariationJsonFilter::class, static fn( Container $c ) => new VariationJsonFilter( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ) ) );
		$c->set( CartFilters::class, static fn( Container $c ) => new CartFilters( $c->get( Settings::class ), $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceHtmlComposer::class ), $c->get( 'request_context' ) ) );
		$c->set( Shortcode::class, static fn( Container $c ) => new Shortcode( $c->get( RenderGuard::class ), $c->get( BadgeDataFactory::class ), $c->get( PriceBadge::class ), $c->get( 'product_loader' ) ) );
		$c->set( Assets::class, static fn( Container $c ) => new Assets( $c->get( Settings::class ) ) );
		$c->set( ExtendStoreApi::class, static fn( Container $c ) => new ExtendStoreApi( $c->get( BadgeDataFactory::class ), $c->get( PriceBadge::class ) ) );

		// History / Omnibus.
		$c->set( PriceHistoryRepository::class, static function (): PriceHistoryRepository {
			global $wpdb;
			return new PriceHistoryRepository( $wpdb, Schema::tableName( $wpdb ) );
		} );
		$c->set( Recorder::class, static fn( Container $c ) => new Recorder( $c->get( PriceHistoryRepository::class ), $c->get( Clock::class ) ) );
		$c->set( LowestPriceCalculator::class, static fn( Container $c ) => new LowestPriceCalculator( $c->get( PriceHistoryRepository::class ) ) );
		$c->set( OmnibusStateUpdater::class, static fn( Container $c ) => new OmnibusStateUpdater( $c->get( LowestPriceCalculator::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );
		$c->set( PriceChangeListener::class, static fn( Container $c ) => new PriceChangeListener( $c->get( ProductAdapter::class ), $c->get( Recorder::class ), $c->get( OmnibusStateUpdater::class ), $c->get( 'product_loader' ) ) );
		$c->set( DailySweep::class, static fn( Container $c ) => new DailySweep( DailySweep::wcPager(), $c->get( 'product_loader' ), $c->get( ProductAdapter::class ), $c->get( Recorder::class ), $c->get( OmnibusStateUpdater::class ) ) );
		$c->set( Pruner::class, static fn( Container $c ) => new Pruner( $c->get( PriceHistoryRepository::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );

		// Scheduling.
		$c->set( SchedulerBackend::class, static fn() => ActionSchedulerBackend::available() ? new ActionSchedulerBackend() : new WpCronBackend() );
		$c->set( Scheduler::class, static fn( Container $c ) => new Scheduler( $c->get( SchedulerBackend::class ), $c->get( Settings::class ), $c->get( Clock::class ) ) );
		$c->set( ServiceChangeDebouncer::class, static fn( Container $c ) => new ServiceChangeDebouncer( $c->get( Scheduler::class ), $c->get( Settings::class ), $c->get( ServiceRule::class ) ) );
	}
}
