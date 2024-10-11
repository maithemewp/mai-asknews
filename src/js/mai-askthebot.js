import { AskNewsSDK } from '@emergentmethods/asknews-typescript-sdk';
import { marked } from 'marked';
import dayjs from 'dayjs';

// DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', () => {
	const chat   = document.getElementById('askthebot-chat');
	const form   = document.getElementById('askthebot-form');
	const bottom = document.getElementById('chat-bottom');
	const button = document.getElementById('chat-down');
	let observer;

	// Bail if we don't have the necessary elements.
	if ( ! ( chat && form && bottom && button ) ) {
		return;
	}

	// Set up the IntersectionObserver.
	function setupObserver() {
		// If an observer already exists, disconnect it first.
		if ( observer ) {
			observer.disconnect();
		}

		// Create a new observer instance
		observer = new IntersectionObserver(handleIntersect, {
			root: null,
			threshold: 1.0
		});

		// Observe.
		observer.observe(bottom);
	}

	// Handle the visibility of the button.
	function handleIntersect(entries) {
		entries.forEach(entry => {
			if (entry.isIntersecting) {
				// Hide the button when the bottom of the chat container is in view
				button.style.display = 'none';
			} else {
				// Show the button when the bottom of the chat container is not in view
				button.style.display = 'block';
			}
		});
	}

	// Set up the observer.
	setupObserver();

	// On click listener.
	button.addEventListener('click', (e) => {
		e.preventDefault();
		scrollToBottom();
	});

	// Form submit event listener.
	form.addEventListener('submit', async (event) => {
		// Prevent the default form submission
		event.preventDefault();

		// Disable the button.
		event.submitter.disabled = true;

		// Add a loading icon or text while waiting for the response.
		event.submitter.innerHTML += '<span class="pm-loading-wrap"><svg class="pm-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Pro 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2024 Fonticons, Inc.--><path class="fa-secondary" opacity=".4" d="M478.7 364.6zm-22 6.1l-27.8-15.9a15.9 15.9 0 0 1 -6.9-19.2A184 184 0 1 1 256 72c5.9 0 11.7 .3 17.5 .8-.7-.1-1.5-.2-2.2-.2-8.5-.7-15.2-7.3-15.2-15.8v-32a16 16 0 0 1 15.3-16C266.2 8.5 261.2 8 256 8 119 8 8 119 8 256s111 248 248 248c98 0 182.4-57 222.7-139.4-4.1 7.9-14.2 10.6-22 6.1z"/><path class="fa-primary" d="M271.2 72.6c-8.5-.7-15.2-7.3-15.2-15.8V24.7c0-9.1 7.7-16.8 16.8-16.2C401.9 17.2 504 124.7 504 256a246 246 0 0 1 -25 108.2c-4 8.2-14.4 11-22.3 6.5l-27.8-15.9c-7.4-4.2-9.8-13.4-6.2-21.1A182.5 182.5 0 0 0 440 256c0-96.5-74.3-175.6-168.8-183.4z"/></svg></span>';

		// Get the input text.
		const textInput = document.getElementById('askthebot-question');

		// Add the new question to the chat.
		bottom.insertAdjacentHTML( 'beforebegin', '<div class="askthebot__message askthebot__user">' + textInput.value + maiAskTheBotVars.userAvatar + '</div>' );

		// Store options.
		const textOptions = [
			'Hold tight, my circuits are buzzing with brilliance!',
			'Calculating… or maybe just daydreaming for a second!',
			'Give me a sec, my digital brain is working its magic!',
			'Fetching brilliance from the AI vault… almost there!',
			'Hold on, I’m putting on my thinking cap… virtually!',
			'Let me consult my code crystals… response incoming!',
			'One moment while I engage super-smart mode!',
			'Thinking faster than a speeding algorithm… almost done!',
			'Give me a nano-second, still loading my best answer!',
		];

		// Built temp HTML.
		const textRandom = textOptions[Math.floor(Math.random() * textOptions.length)];
		const imgString  = '<img src="/wp-content/plugins/mai-asknews/src/img/chad-head.png" alt="robot head with headset" />';
		const tpmString  = '<div class="askthebot-loading">' + imgString + '<span class="askthebot-loading__text">' + textRandom + '</span><span class="pm-loading-wrap"><svg class="pm-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Pro 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2024 Fonticons, Inc.--><path class="fa-secondary" opacity=".4" d="M478.7 364.6zm-22 6.1l-27.8-15.9a15.9 15.9 0 0 1 -6.9-19.2A184 184 0 1 1 256 72c5.9 0 11.7 .3 17.5 .8-.7-.1-1.5-.2-2.2-.2-8.5-.7-15.2-7.3-15.2-15.8v-32a16 16 0 0 1 15.3-16C266.2 8.5 261.2 8 256 8 119 8 8 119 8 256s111 248 248 248c98 0 182.4-57 222.7-139.4-4.1 7.9-14.2 10.6-22 6.1z"/><path class="fa-primary" d="M271.2 72.6c-8.5-.7-15.2-7.3-15.2-15.8V24.7c0-9.1 7.7-16.8 16.8-16.2C401.9 17.2 504 124.7 504 256a246 246 0 0 1 -25 108.2c-4 8.2-14.4 11-22.3 6.5l-27.8-15.9c-7.4-4.2-9.8-13.4-6.2-21.1A182.5 182.5 0 0 0 440 256c0-96.5-74.3-175.6-168.8-183.4z"/></svg></span></div>';

		// Add before form.
		bottom.insertAdjacentHTML( 'beforebegin', tpmString );

		// Scroll to the bottom of the chat container.
		scrollToBottom();

		// Collect form data.
		const formData = new FormData(form);

		// Remove the text input value, after form data is collected.
		textInput.value = '';

		// Send the form data to the server using Fetch API.
		fetch( maiAskTheBotVars.ajaxUrl, {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then( response => response.json() ) // Assuming the server returns JSON
		.then( data => {
			// Get the message.
			let message = data.data.message;

			// Get the date pattern.
			const datePattern = /(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\+\d{2}:\d{2})/g;

			// Replace function to find and convert the dates
			message = message.replace(datePattern, (match) => {
				return dayjs(match).format('MMM D, YYYY @ h:mm a');
			});

			// Remove the loading spinner.
			chat.querySelector('.askthebot-loading').remove();

			// Add before the form.
			bottom.insertAdjacentHTML( 'beforebegin', '<div class="askthebot__message askthebot__bot">' + marked( message ) + '</div>' );

			// Reset the observer.
			setupObserver();

			// Remove the input value.
			textInput.value = '';

			// Enable the button.
			event.submitter.disabled = false;

			// Remove the loading icon.
			event.submitter.querySelector('.pm-loading-wrap').remove();
		})
		.catch( error => {
			console.error( 'promatchups error:', error );
		});
	});

	// Scroll to the bottom of the chat container.
	function scrollToBottom() {
		// Scroll the body to align the bottom of the chat container with the bottom of the viewport.
		window.scrollTo({
			top: chat.getBoundingClientRect().bottom + window.scrollY - window.innerHeight,
			behavior: 'smooth'
		});
	}
});