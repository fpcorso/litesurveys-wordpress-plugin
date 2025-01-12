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

	<?php if (empty($submissions) && !isset($_GET['s'])) : ?>
		<div class="notice notice-warning">
			<p><?php _e('This survey has not received any submissions yet.', 'litesurveys'); ?></p>
		</div>
	<?php else : ?>
		<form method="get">
			<input type="hidden" name="page" value="LSAPP_litesurveys">
			<input type="hidden" name="action" value="view-responses">
			<input type="hidden" name="id" value="<?php echo esc_attr($survey_id); ?>">
			
			<p class="search-box">
				<label class="screen-reader-text" for="submission-search-input">
					<?php esc_html_e('Search submissions', 'litesurveys'); ?>
				</label>
				<input type="search" id="submission-search-input" name="s" 
					value="<?php echo isset($_GET['s']) ? esc_attr(wp_unslash($_GET['s'])) : ''; ?>"
					placeholder="<?php esc_attr_e('Search submissions...', 'litesurveys'); ?>"
					aria-describedby="search-results-info">
				
				<input type="submit" class="button" value="<?php esc_attr_e('Search Submissions', 'litesurveys'); ?>">
				
				<?php if (isset($_GET['s']) && !empty($_GET['s'])) : ?>
					<a href="<?php echo esc_url(add_query_arg(array(
						'page' => 'LSAPP_litesurveys',
						'action' => 'view-responses',
						'id' => $survey_id
					), admin_url('admin.php'))); ?>" 
					class="button">
						<?php esc_html_e('Clear Search', 'litesurveys'); ?>
					</a>
				<?php endif; ?>
			</p>
		</form>

		<div class="tablenav top">
			<div class="tablenav-pages">
				<span class="displaying-num" id="search-results-info">
					<?php 
					if (isset($_GET['s']) && !empty($_GET['s'])) {
						printf(
							/* translators: 1: Number of results, 2: Search query */
							esc_html(_n(
								'%1$s result found for "%2$s"',
								'%1$s results found for "%2$s"',
								$total_items,
								'litesurveys'
							)),
							number_format_i18n($total_items),
							esc_html(wp_unslash($_GET['s']))
						);
					} else {
						printf(
							esc_html(_n(
								'%s submission',
								'%s submissions',
								$total_items,
								'litesurveys'
							)),
							number_format_i18n($total_items)
						);
					}
					?>
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
					<th scope="col"><?php esc_html_e('Submission Time', 'litesurveys'); ?></th>
					<th scope="col"><?php esc_html_e('Answer', 'litesurveys'); ?></th>
					<th scope="col"><?php esc_html_e('Page', 'litesurveys'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($submissions)) : ?>
					<tr>
						<td colspan="3">
							<?php esc_html_e('No submissions found matching your search.', 'litesurveys'); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ($submissions as $submission) : ?>
						<tr>
							<td>
								<?php echo esc_html(
									date_i18n(
										get_option('date_format') . ' ' . get_option('time_format'), 
										strtotime($submission->created_at)
									)
								); ?>
								<div class="row-actions">
									<span class="trash">
										<a href="<?php echo wp_nonce_url(
											admin_url(sprintf(
												'admin-post.php?action=delete_submission&id=%d&survey_id=%d',
												$submission->id,
												$survey_id
											)),
											'delete-submission_' . $submission->id
										); ?>" 
										class="submitdelete" 
										onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this submission?', 'litesurveys'); ?>');">
											<?php esc_html_e('Delete', 'litesurveys'); ?>
										</a>
									</span>
								</div>
							</td>
							<td><?php echo esc_html($submission->response); ?></td>
							<td><?php echo esc_html($submission->page); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
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