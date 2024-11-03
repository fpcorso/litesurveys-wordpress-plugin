<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'litesurveys'));
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline">LiteSurveys</h1>
	<a href="<?php echo esc_url(admin_url('admin.php?page=litesurveys&action=new')); ?>" class="page-title-action">
		<?php _e('Add New Survey', 'litesurveys'); ?>
	</a>
	
	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" class="manage-column"><?php _e('Survey Name', 'litesurveys'); ?></th>
				<th scope="col" class="manage-column"><?php _e('Status', 'litesurveys'); ?></th>
				<th scope="col" class="manage-column"><?php _e('Responses', 'litesurveys'); ?></th>
				<th scope="col" class="manage-column"><?php _e('Created', 'litesurveys'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($surveys)) : ?>
				<tr>
					<td colspan="4">
						<?php _e('No surveys found.', 'litesurveys'); ?> 
						<a href="<?php echo esc_url(admin_url('admin.php?page=litesurveys&action=new')); ?>">
							<?php _e('Create your first survey', 'litesurveys'); ?>
						</a>.
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ($surveys as $survey) : ?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url(admin_url('admin.php?page=litesurveys&action=edit&id=' . $survey->id)); ?>">
									<?php echo esc_html($survey->name); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url(admin_url('admin.php?page=litesurveys&action=edit&id=' . $survey->id)); ?>">
										<?php _e('Edit', 'litesurveys'); ?>
									</a> | 
								</span>
								<span class="view">
									<a href="<?php echo esc_url(admin_url('admin.php?page=litesurveys&action=view-responses&id=' . $survey->id)); ?>">
										<?php _e('View Responses', 'litesurveys'); ?>
									</a> | 
								</span>
								<span class="trash">
									<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=litesurveys&action=delete&id=' . $survey->id), 'delete-survey_' . $survey->id); ?>" 
									   class="submitdelete">
										<?php _e('Delete', 'litesurveys'); ?>
									</a>
								</span>
							</div>
						</td>
						<td>
							<?php if ($survey->active) : ?>
								<span class="status-active"><?php _e('Active', 'litesurveys'); ?></span>
							<?php else : ?>
								<span class="status-inactive"><?php _e('Draft', 'litesurveys'); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$response_count = $wpdb->get_var($wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->prefix}litesurveys_submissions WHERE survey_id = %d",
								$survey->id
							));
							echo esc_html($response_count);
							?>
						</td>
						<td>
							<?php echo esc_html(mysql2date(get_option('date_format'), $survey->created_at)); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>