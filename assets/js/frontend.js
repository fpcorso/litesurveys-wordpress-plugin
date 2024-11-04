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
};