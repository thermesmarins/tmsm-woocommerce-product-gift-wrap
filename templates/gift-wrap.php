<?php
/**
 * @param WC_Product $product
 * @param string $price_text
 * @param string $checkbox Checkbox
 * @param string $product_gift_wrap_message
 */
?>

<p class="gift-wrapping gift-wrapping<?php echo ( $product->is_type( 'variation' ) ? '-variation' : '' ) ?>" id="gift-wrapping-variation-<?php echo $product->get_id()?>">
	<label><?php echo str_replace( array( '{checkbox}', '{price}' ), array( $checkbox, $price_text ), wp_kses_post( $product_gift_wrap_message ) ); ?></label>
</p>
