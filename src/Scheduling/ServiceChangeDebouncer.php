<?php
/**
 * Regenerates the price list shortly after a (service) price change – debounced and unique.
 *
 * @package SidrenaCijena
 */

declare(strict_types=1);

namespace SidrenaCijena\Scheduling;

use SidrenaCijena\Product\ProductSnapshot;
use SidrenaCijena\Product\ServiceRule;
use SidrenaCijena\Settings\Settings;

final class ServiceChangeDebouncer {

	public function __construct(
		private readonly Scheduler $scheduler,
		private readonly Settings $settings,
		private readonly ServiceRule $serviceRule,
	) {}

	public function register(): void {
		add_action( 'scwc_price_changed', [ $this, 'onPriceChanged' ], 10, 2 );
		add_action( 'scwc_product_removed', [ $this, 'onProductRemoved' ] );
		add_action( 'scwc_snapshot_completed', [ $this, 'onBulkChange' ] );
		add_action( 'scwc_import_completed', [ $this, 'onBulkChange' ] );
	}

	/**
	 * @param mixed $snapshot   ProductSnapshot.
	 * @param mixed $transition Transition string.
	 */
	public function onPriceChanged( $snapshot, $transition = '' ): void {
		if ( ! $snapshot instanceof ProductSnapshot ) {
			return;
		}
		$mode = (string) $this->settings->get( 'price_list.regenerate_on_change', 'services' );
		if ( 'never' === $mode ) {
			return;
		}
		if ( 'services' === $mode && ! $this->serviceRule->isService( $snapshot ) ) {
			return;
		}
		$this->touch();
	}

	/**
	 * @param mixed $productId Removed product ID.
	 */
	public function onProductRemoved( $productId = 0 ): void {
		$this->touch();
	}

	public function onBulkChange(): void {
		$this->touch();
	}

	public function touch(): void {
		if ( ! (bool) $this->settings->get( 'price_list.enabled', true ) || 'never' === $this->settings->get( 'price_list.regenerate_on_change', 'services' ) ) {
			return;
		}
		update_option( 'scwc_services_dirty_at', time(), false );
		$this->scheduler->scheduleGenerationSoon( (int) $this->settings->get( 'price_list.debounce_seconds', 300 ), 'change' );
	}
}
