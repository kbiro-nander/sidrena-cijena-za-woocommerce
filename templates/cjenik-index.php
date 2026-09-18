<?php
/**
 * Public price-list index page (/cjenik/). Override: yourtheme/sidrena-cijena/cjenik-index.php
 *
 * @package SidrenaCijena
 * @var array<string,string>            $outlet
 * @var array<string,string>            $latest
 * @var array<int,array<string,mixed>>  $files
 * @var string                          $base_url
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( sprintf( 'Cjenik – %s', $outlet['merchant_name'] ) ); ?></title>
<style>
body{font:16px/1.5 system-ui,sans-serif;margin:0;padding:24px 16px;background:#fafafa;color:#1d1d1f}
main{max-width:960px;margin:0 auto}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{padding:8px 10px;border-bottom:1px solid #e5e5e5;text-align:left;font-size:14px}
th{background:#f0f0f0}
.latest a{display:inline-block;margin:0 12px 8px 0;padding:8px 14px;background:#1d4ed8;color:#fff;border-radius:6px;text-decoration:none}
small{color:#555}
.wrap{overflow-x:auto}
</style>
</head>
<body>
<main>
<h1>Cjenik proizvoda i usluga</h1>
<p><strong><?php echo esc_html( $outlet['merchant_name'] ); ?></strong><br>
<?php echo esc_html( $outlet['form'] ); ?> · <?php echo esc_html( $outlet['address'] ); ?> · <?php echo esc_html__( 'oznaka', 'sidrena-cijena-za-woocommerce' ); ?>: <?php echo esc_html( $outlet['label'] ); ?> · <?php echo esc_html__( 'broj pohrane', 'sidrena-cijena-za-woocommerce' ); ?>: <?php echo esc_html( $outlet['storage_number'] ); ?></p>
<p><small><?php echo esc_html__( 'Strojno čitljiv cjenik objavljen prema Odluci o objavi cjenika proizvoda i usluga (NN 101/2026). Datoteke sadrže maloprodajne cijene, sidrene cijene i podatke o posebnim oblicima prodaje. Verzije se čuvaju najmanje 30 dana.', 'sidrena-cijena-za-woocommerce' ); ?></small></p>
<p class="latest">
<?php foreach ( $latest as $format => $url ) : ?>
	<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( sprintf( 'Najnoviji cjenik (%s)', strtoupper( (string) $format ) ) ); ?></a>
<?php endforeach; ?>
</p>
<div class="wrap">
<table>
<thead><tr><th><?php echo esc_html__( 'Datoteka', 'sidrena-cijena-za-woocommerce' ); ?></th><th><?php echo esc_html__( 'Format', 'sidrena-cijena-za-woocommerce' ); ?></th><th><?php echo esc_html__( 'Generirano', 'sidrena-cijena-za-woocommerce' ); ?></th><th><?php echo esc_html__( 'Proizvodi', 'sidrena-cijena-za-woocommerce' ); ?></th><th><?php echo esc_html__( 'Usluge', 'sidrena-cijena-za-woocommerce' ); ?></th><th><?php echo esc_html__( 'Veličina', 'sidrena-cijena-za-woocommerce' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $files as $f ) : ?>
<tr>
<td><a href="<?php echo esc_url( (string) $f['url'] ); ?>"><?php echo esc_html( (string) $f['name'] ); ?></a></td>
<td><?php echo esc_html( strtoupper( (string) $f['format'] ) ); ?></td>
<td><?php echo esc_html( (string) $f['generated_at'] ); ?></td>
<td><?php echo (int) $f['products']; ?></td>
<td><?php echo (int) $f['services']; ?></td>
<td><?php echo esc_html( number_format( (int) $f['size'] / 1024, 1, ',', '.' ) ); ?> kB</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p><small><a href="<?php echo esc_url( $base_url . 'index.json' ); ?>">index.json</a></small></p>
</main>
</body>
</html>
