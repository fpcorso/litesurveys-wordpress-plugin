<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'litesurveys'));
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php printf(__('Submissions: %s', 'litesurveys'), esc_html($survey->name)); ?>
	</h1>
	
	<a href="<?php echo esc_url(admin_url('admin.php?page=LSAPP_litesurveys')); ?>" class="page-title-action">
		<?php _e('← Back to Surveys', 'litesurveys'); ?>
	</a>
	
	<hr class="wp-header-end">

	<?php if (empty($submissions)) : ?>
		<div class="notice notice-warning">
			<p><?php _e('This survey has not received any submissions yet.', 'litesurveys'); ?></p>
		</div>
	<?php else : ?>
		<div class="tablenav top">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php printf(
						_n('%s submission', '%s submissions', $total_items, 'litesurveys'),
						number_format_i18n($total_items)
					); ?>
				</span>
				<?php if ($total_pages > 1) : 
					$page_links = paginate_links(array(
						'base' => add_query_arg('paged', '%#%'),
						'format' => '',
						'prev_text' => __('&laquo;'),
						'next_text' => __('&raquo;'),
						'total' => $total_pages,
						'current' => $current_page,
						'type' => 'array'
					));
					
					if ($page_links) : ?>
						<span class="pagination-links">
							<?php echo join("\n", array_map(function($link) {
								// Add button class to maintain WordPress admin styling
								return str_replace(
									array('<a ', 'current'),
									array('<a class="button" ', 'button-primary current'),
									$link
								);
							}, $page_links)); ?>
						</span>
					<?php endif; 
				endif; ?>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php _e('Submission Time', 'litesurveys'); ?></th>
					<th scope="col"><?php _e('Answer', 'litesurveys'); ?></th>
					<th scope="col"><?php _e('Page', 'litesurveys'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($submissions as $submission) : ?>
					<tr>
						<td>
							<?php echo esc_html(
								date_i18n(
									get_option('date_format') . ' ' . get_option('time_format'), 
									strtotime($submission->created_at)
								)
							); ?>
						</td>
						<td><?php echo esc_html($submission->response); ?></td>
						<td><?php echo esc_html($submission->page); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ($total_pages > 1) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php if ($total_pages > 1) : 
						$page_links = paginate_links(array(
							'base' => add_query_arg('paged', '%#%'),
							'format' => '',
							'prev_text' => __('&laquo;'),
							'next_text' => __('&raquo;'),
							'total' => $total_pages,
							'current' => $current_page,
							'type' => 'array'
						));
						
						if ($page_links) : ?>
							<span class="pagination-links">
								<?php echo join("\n", array_map(function($link) {
									// Add button class to maintain WordPress admin styling
									return str_replace(
										array('<a ', 'current'),
										array('<a class="button" ', 'button-primary current'),
										$link
									);
								}, $page_links)); ?>
							</span>
						<?php endif; 
					endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>