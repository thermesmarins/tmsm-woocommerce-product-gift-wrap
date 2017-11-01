<?php
/**
 * @param $product WC_Product
 * @param $price_text Price text
 * @param $checkbox Checkbox value
 * @param $product_gift_wrap_message Wrap message
 */
?>

<p class="gift-wrapping gift-wrapping<?php echo ( $product->is_type( 'variation' ) ? '-variation' : '' ) ?>" id="gift-wrapping-variation-<?php echo $product->get_id()?>">
	<label><?php echo str_replace( array( '{checkbox}', '{price}' ), array( $checkbox, $price_text ), wp_kses_post( $product_gift_wrap_message ) ); ?></label>
</p>
