<?php
$title              = isset( $title ) ? $title : '';
$more_info          = isset( $more_info ) ? $more_info : '';
$total_progress     = isset( $total_progress ) ? (int) $total_progress : 0;
$progress           = isset( $progress ) ? (int) $progress : 0;
$total_on_provider  = isset( $total_on_provider ) ? (int) $total_on_provider : 0;
$total_items        = isset( $total_items ) ? (int) $total_items : 0;
$is_queued          = isset( $is_queued ) ? $is_queued : false;
$is_paused          = isset( $is_paused ) ? $is_paused : false;
$is_cancelled       = isset( $is_cancelled ) ? $is_cancelled : false;
$is_upgrading       = isset( $is_upgrading ) ? $is_upgrading : false;
$status_description = isset( $status_description ) ? $status_description : '';
$busy_description   = isset( $busy_description ) ? $busy_description : '';
$button             = isset( $button ) ? $button : '';
$queue              = isset( $queue ) ? $queue : array();

$pie_title = $total_progress . '% (' . $total_on_provider . ' / ' . $total_items . ')';

$progress_title = $status_description;

if ( $is_queued && ! $is_cancelled && isset( $queue['processed'] ) && ! empty( $queue['total'] ) ) {
	$progress_title = $progress . '% (' . $queue['processed'] . ' / ' . $queue['total'] . ')';
}
?>

<div class="block-scope <?php echo $total_progress === 100 ? 'completed' : '' ?>"
	 data-state='<?php echo wp_json_encode( $args ) ?>'>
	<div class="block-title-wrap <?php echo ! empty( $more_info ) ? 'with-description' : ''; ?>">
		<?php if ( ! empty( $pie_chart ) ) : ?>
			<div class="pie-chart" title="<?php echo $pie_title; ?>" data-percentage="<?php echo $total_progress; ?>">
				<svg viewBox="-100 -100 200 200">
					<path d=""/>
				</svg>
			</div>
		<?php endif ?>

		<h4 class="block-title"><?php echo $title; ?></h4>

		<?php if ( ! empty ( $more_info ) ) : ?>
			<a href="#" class="general-helper"></a>
			<div class="helper-message">
				<?php echo $more_info; ?>
			</div>
		<?php endif; ?>
	</div>

	<p class="block-description" style="display: <?php echo $status_description ? 'block' : 'none'; ?>;">
		<?php echo $status_description; ?>
	</p>

	<div class="progress-bar-wrapper <?php echo $is_paused ? 'paused' : ''; ?>"
		 style="display: <?php echo $is_queued ? 'block' : 'none'; ?>;" title="<?php echo $progress_title; ?>">
		<div class="progress-bar" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
	</div>

	<?php if ( ! empty( $button ) ) : ?>
		<div class="button-wrapper <?php echo $is_queued ? 'processing' : '';
		echo $is_upgrading ? 'upgrading' : ''; ?>">
			<a href="#" id="as3cf-<?php echo $slug; ?>-start" class="start button"
			   data-busy-description="<?php echo $busy_description; ?>">
				<?php echo $button; ?>
			</a>
			<a href="#" id="as3cf-<?php echo $slug; ?>-pause" class="pause pause-resume button"
			   data-busy-description="<?php _e( 'Pausing&hellip;', 'amazon-s3-and-cloudfront' ); ?>"
			   style="display: <?php echo ( $is_queued && ! $is_paused ) ? 'inline-block' : 'none'; ?>;">
				<?php _e( 'Pause', 'amazon-s3-and-cloudfront' ); ?>
			</a>
			<a href="#" id="as3cf-<?php echo $slug; ?>-resume" class="resume pause-resume button"
			   data-busy-description="<?php _e( 'Resuming&hellip;', 'amazon-s3-and-cloudfront' ); ?>"
			   style="display: <?php echo ( $is_queued && $is_paused ) ? 'inline-block' : 'none'; ?>;">
				<?php _e( 'Resume', 'amazon-s3-and-cloudfront' ); ?>
			</a>
			<a href="#" id="as3cf-<?php echo $slug; ?>-cancel" class="cancel button"
			   data-busy-description="<?php _e( 'Cancelling&hellip;', 'amazon-s3-and-cloudfront' ); ?>">
				<?php _e( 'Cancel', 'amazon-s3-and-cloudfront' ); ?>
			</a>
			<a href="#" id="as3cf-<?php echo $slug; ?>-upgrading" class="upgrading button" disabled="disabled"
			   data-busy-description="<?php _e( 'Disabled during upgrade&hellip;', 'amazon-s3-and-cloudfront' ); ?>">
				<?php _e( 'Upgrading&hellip;', 'amazon-s3-and-cloudfront' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div><!-- /.block-scope -->
