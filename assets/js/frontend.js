// assets/js/frontend.js
const liteSurveys = {
	surveys: [],
	templates: {
		modal: `
			<div class="litesurveys-slidein" tabindex="-1" role="dialog">
				<button class="litesurveys-slidein-close" aria-label="Close"></button>
				<div class="litesurveys-slidein-content">
					<div class="litesurveys-slidein-content-form">
						<div class="litesurveys-slide-content-field">
							<label class="litesurveys-slide-content-label"></label>
							<div class="litesurveys-slide-content-control"></div>
						</div>
					</div>
				</div>
			</div>
		`,
		answerButton: `<button class="litesurveys-answer-button"></button>`,
		openAnswer: `<textarea class="litesurveys-slidein-content-textarea"></textarea>`,
		submitButton: `<button class="litesurveys-slidein-content-button">Submit</button>`
	},

	init() {
		this.loadSurveys();
	},

	loadSurveys() {
		fetch(liteSurveysSettings.ajaxUrl + 'surveys')
			.then(response => response.json())
			.then(surveys => {
				if (!this.hasValidSurveys(surveys)) {
					return;
				}
				this.surveys = surveys;
				this.prepareSurvey();
			});
	},

	prepareSurvey() {
		this.addModalMarkup();
		this.addSlideinListeners();
		this.setPosition();

		// Add trigger based on settings
		const trigger = this.surveys[0].targeting_settings.trigger[0];
		if (trigger.type === 'auto') {
			setTimeout(() => this.openModal(), trigger.auto_timing * 1000);
		} else if (trigger.type === 'exit') {
			document.addEventListener('mouseout', (event) => {
				if (event.clientY <= 10) {
					this.openModal();
				}
			});
		}
	},

	addModalMarkup() {
		// Create modal element
		const modalElement = document.createElement('div');
		modalElement.innerHTML = this.templates.modal.trim();
		const modal = modalElement.firstChild;

		// Add question content
		const question = this.surveys[0].questions[0];
		modal.querySelector('.litesurveys-slide-content-label').textContent = question.content;

		const controlDiv = modal.querySelector('.litesurveys-slide-content-control');
		if (question.type === 'multiple-choice') {
			question.answers.forEach(answer => {
				const button = document.createElement('div');
				button.innerHTML = this.templates.answerButton.trim();
				button.firstChild.textContent = answer;
				controlDiv.appendChild(button.firstChild);
			});
		} else {
			// Open answer type
			const textarea = document.createElement('div');
			textarea.innerHTML = this.templates.openAnswer.trim();
			controlDiv.appendChild(textarea.firstChild);

			const submitBtn = document.createElement('div');
			submitBtn.innerHTML = this.templates.submitButton.trim();
			controlDiv.appendChild(submitBtn.firstChild);
		}

		document.body.appendChild(modal);
	},

	setPosition() {
		const horizontalPosition = this.surveys[0].appearance_settings?.horizontal_position || 'right';
		if (horizontalPosition === 'left') {
			document.documentElement.style.setProperty('--litesurveys-slidein-left-spacing', '1em');
		} else {
			document.documentElement.style.setProperty('--litesurveys-slidein-right-spacing', '1em');
		}
	},

	addSlideinListeners() {
		const modal = document.querySelector('.litesurveys-slidein');

		// Close button
		modal.querySelector('.litesurveys-slidein-close').addEventListener('click', () => {
			this.closeModal();
		});

		// Multiple choice answers
		modal.querySelectorAll('.litesurveys-answer-button').forEach(button => {
			button.addEventListener('click', (e) => {
				this.submitSurvey(e.target.textContent);
			});
		});

		// Open answer submit
		const submitButton = modal.querySelector('.litesurveys-slidein-content-button');
		if (submitButton) {
			submitButton.addEventListener('click', () => {
				const answer = modal.querySelector('.litesurveys-slidein-content-textarea').value;
				if (answer.trim()) {
					this.submitSurvey(answer.trim());
				}
			});
		}

		// Escape key handler
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27) {
				this.closeModal();
			}
		});
	},

	openModal() {
		const cookieName = `litesurveys-slideinclosed-${this.surveys[0].id}`;
		if (!this.getCookie(cookieName)) {
			document.querySelector('.litesurveys-slidein').classList.add('litesurveys-is-active');
			document.querySelector('.litesurveys-slidein').focus();
		}
	},

	closeModal() {
		document.querySelector('.litesurveys-slidein').classList.remove('litesurveys-is-active');
		this.setCookie(`litesurveys-slideinclosed-${this.surveys[0].id}`, 'yes', 365);
	},

	submitSurvey(answer) {
		const survey = this.surveys[0];
		const submission = {
			responses: [{
				question_id: survey.questions[0].id,
				content: answer
			}],
			page: window.location.href
		};

		fetch(liteSurveysSettings.ajaxUrl + `surveys/${survey.id}/submissions`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(submission)
		}).then(() => {
			// Replace content with thank you message
			const content = document.querySelector('.litesurveys-slidein-content');
			const form = content.querySelector('.litesurveys-slidein-content-form');
			form.innerHTML = survey.submit_message;

			// Set cookie to prevent showing again
			this.setCookie(`litesurveys-slideinclosed-${survey.id}`, 'yes', 365);

			// Close after delay
			setTimeout(() => this.closeModal(), 3000);
		});
	},

	hasValidSurveys(surveys) {
		return Array.isArray(surveys) && surveys.length > 0 && surveys[0]?.id;
	},

	setCookie(name, value, days) {
		const date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/;samesite=lax`;
	},

	getCookie(name) {
		const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
		return match ? match[2] : null;
	}
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => liteSurveys.init());
} else {
	liteSurveys.init();
}