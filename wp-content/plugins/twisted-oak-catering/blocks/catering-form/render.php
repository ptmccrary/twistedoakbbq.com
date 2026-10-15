<?php
/**
 * Server render for twisted-oak/catering-form.
 *
 * @package TwistedOakCatering
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped. ?>>
	<?php echo twistedoak_catering_render_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer. ?>
</div>
