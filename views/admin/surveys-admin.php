<?php
// Exits if accessed directly.
defined('ABSPATH') or die('Direct access not permitted.');
?>
<div class="wrap">
	<h1 class="wp-heading-inline">LiteSurveys</h1>
	<a href="<?php echo admin_url('admin.php?page=litesurveys&action=new'); ?>" class="page-title-action">Add New Survey</a>
	
	<hr class="wp-header-end">

	<div class="tablenav top">
		<div class="alignleft actions">
			<select name="bulk-action">
				<option value="-1">Bulk Actions</option>
				<option value="delete">Delete</option>
				<option value="activate">Activate</option>
				<option value="deactivate">Deactivate</option>
			</select>
			<input type="submit" class="button action" value="Apply">
		</div>
		<div class="tablenav-pages">
			<!-- Pagination would go here -->
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<td class="manage-column column-cb check-column">
					<input type="checkbox">
				</td>
				<th scope="col" class="manage-column">Survey Name</th>
				<th scope="col" class="manage-column">Status</th>
				<th scope="col" class="manage-column">Responses</th>
				<th scope="col" class="manage-column">Created</th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($surveys)) : ?>
				<tr>
					<td colspan="5">No surveys found. <a href="<?php echo admin_url('admin.php?page=litesurveys&action=new'); ?>">Create your first survey</a>.</td>
				</tr>
			<?php else : ?>
				<?php foreach ($surveys as $survey) : ?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox" name="surveys[]" value="<?php echo esc_attr($survey->id); ?>">
						</th>
						<td>
							<strong>
								<a href="<?php echo admin_url('admin.php?page=litesurveys&action=edit&id=' . $survey->id); ?>">
									<?php echo esc_html($survey->name); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo admin_url('admin.php?page=litesurveys&action=edit&id=' . $survey->id); ?>">Edit</a> |
								</span>
								<span class="trash">
									<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=litesurveys&action=delete&id=' . $survey->id), 'delete-survey_' . $survey->id); ?>" class="submitdelete">Delete</a>
								</span>
							</div>
						</td>
						<td>
							<?php echo $survey->active ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; ?>
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
							<?php echo esc_html(mysql2date('F j, Y', $survey->created_at)); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>