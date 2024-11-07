<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'litesurveys'));
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php echo $survey_id ? 'Edit Survey: ' . esc_html($survey->name) : 'Create New Survey'; ?>
	</h1>
	
	<a href="<?php echo esc_url(admin_url('admin.php?page=LSAPP_litesurveys')); ?>" class="page-title-action">
		<?php _e('← Back to Surveys', 'litesurveys'); ?>
	</a>
	
	<hr class="wp-header-end">
	
	<form id="survey-edit-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
		<?php wp_nonce_field('save_survey', 'survey_nonce'); ?>
		<input type="hidden" name="action" value="save_survey">
		<input type="hidden" name="survey_id" value="<?php echo esc_attr($survey_id); ?>">
		
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<!-- Main Survey Settings -->
					<div class="postbox">
						<div class="postbox-header">
							<h2>Survey Settings</h2>
						</div>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="survey-name">Survey Name</label>
									</th>
									<td>
										<input name="survey_name" type="text" id="survey-name" 
											   value="<?php echo esc_attr($survey->name); ?>" 
											   class="regular-text">
										<p class="description">
											This is never shown to your site visitors. This is only to help you find this survey.
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- Question Settings -->
					<div class="postbox">
						<div class="postbox-header">
							<h2>Survey Question</h2>
						</div>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="question-type">Question Type</label>
									</th>
									<td>
										<select name="question_type" id="question-type">
											<option value="multiple-choice" 
												<?php selected($survey->question->type, 'multiple-choice'); ?>>
												Multiple Choice
											</option>
											<option value="open-answer" 
												<?php selected($survey->question->type, 'open-answer'); ?>>
												Open Answer
											</option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="question-content">Question</label>
									</th>
									<td>
										<div class="question-content-wrapper">
											<textarea name="question_content" id="question-content" 
													class="large-text" rows="3"><?php 
												echo esc_textarea($survey->question->content); 
											?></textarea>
										</div>
									</td>
								</tr>

								<!-- Answer Options Section -->
								<tr class="answer-options" style="<?php echo $survey->question->type === 'open-answer' ? 'display:none' : ''; ?>">
									<th scope="row">Answer Choices</th>
									<td>
										<div class="answer-choices-wrapper">
											<div id="answer-choices">
												<?php foreach ($survey->question->answers as $index => $answer) : ?>
													<div class="answer-choice">
														<input type="text" name="answers[]" 
															value="<?php echo esc_attr($answer); ?>" 
															class="regular-text">
														<button type="button" class="button remove-answer">Remove</button>
													</div>
												<?php endforeach; ?>
											</div>
											<button type="button" class="button add-answer">Add Answer</button>
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- Targeting Settings -->
					<div class="postbox">
						<div class="postbox-header">
							<h2>Survey Targeting</h2>
						</div>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th scope="row">Show this survey on...</th>
									<td>
										<fieldset>
											<label>
												<input type="radio" name="targeting_show" value="all" checked disabled>
												... all pages
											</label>
											<input type="hidden" name="targeting_show" value="all">
											<p class="description" style="margin-top: 10px;">
												<?php printf(
													__('More targeting options coming soon! Want to show your survey on specific pages? <a href="%s" target="_blank">Let us know</a> what targeting options would be most useful for you.', 'litesurveys'),
													'https://litesurveys.com/contact.html'
												); ?>
											</p>
										</fieldset>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- Display Settings -->
					<div class="postbox">
						<div class="postbox-header">
							<h2>Display Settings</h2>
						</div>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="submit-message">Confirmation Message</label>
									</th>
									<td>
										<textarea name="submit_message" id="submit-message" 
												  class="large-text" rows="3"><?php 
											echo esc_textarea($survey->submit_message); 
										?></textarea>
										<p class="description">
											This is shown to the user after submitting the survey.
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">Show this survey when...</th>
									<td>
										<fieldset>
											<label>
												<input type="radio" name="trigger_type" value="auto" checked disabled>
												... a user has been on the page for at least 
												<input type="number" name="auto_timing" 
													value="<?php echo esc_attr($survey->targeting_settings->trigger[0]->auto_timing); ?>"
													min="0" step="1" style="width: 60px;"> seconds
											</label>
											<input type="hidden" name="trigger_type" value="auto">
											<p class="description" style="margin-top: 10px;">
												<?php printf(
													__('More trigger options coming soon! Want to show your survey on exit intent or scroll? <a href="%s" target="_blank">Let us know</a> what trigger options would help you collect better feedback.', 'litesurveys'),
													'https://litesurveys.com/contact.html'
												); ?>
											</p>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row">Display Position</th>
									<td>
										<fieldset>
											<label>
												<input type="radio" name="horizontal_position" value="left"
													<?php checked($survey->appearance_settings->horizontal_position, 'left'); ?>>
												Bottom left
											</label><br>
											<label>
												<input type="radio" name="horizontal_position" value="right"
													<?php checked($survey->appearance_settings->horizontal_position, 'right'); ?>>
												Bottom right
											</label>
										</fieldset>
									</td>
								</tr>
							</table>
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div id="postbox-container-1" class="postbox-container">
					<div class="postbox">
						<div class="postbox-header">
							<h2>Survey Status</h2>
						</div>
						<div class="inside">
							<div class="submitbox">
								<input type="hidden" name="save_type" id="save_type" value="draft">
								<?php if ($survey->active) : ?>
									<div style="margin-bottom: 10px;">
										<button type="submit" class="button button-primary button-large">
											<?php esc_html_e('Save Changes', 'litesurveys'); ?>
										</button>
									</div>
									<div>
										<button type="submit" class="button button-secondary button-large" style="color: #d63638;">
											<?php esc_html_e('Save & Unpublish Survey', 'litesurveys'); ?>
										</button>
									</div>
								<?php else : ?>
									<button type="submit" class="button button-primary button-large">
										<?php esc_html_e('Save & Publish Survey', 'litesurveys'); ?>
									</button>
									<div style="margin-top: 10px;">
										<button type="submit" class="button button-large">
											<?php echo esc_html('Save Draft'); ?>
										</button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>