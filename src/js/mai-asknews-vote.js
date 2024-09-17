document.addEventListener('DOMContentLoaded', function () {
	// Get the form element.
	const form = document.querySelector('.pm-vote__form');

	// If the form element does not exist, stop the script.
	if ( ! form ) {
		return;
	}

	// On form submit.
	form.addEventListener('submit', function (event) {
		// Prevent the default form submission
		event.preventDefault();

		// Disable the button.
		event.submitter.disabled = true;

		// Get the selected element.
		let selectedEl = form.querySelector('.pm-outcome__selected');

		// If we have one, fade out then remove it.
		if ( selectedEl ) {
			selectedEl.remove();
		}

		// Collect form data.
		const formData = new FormData(form);

		// Add the team value from the button that was clicked.
		formData.append( 'team', event.submitter.value );

		// Store the original button text.
		let buttonText = '';

		// Loop through the child nodes to find the text node.
		event.submitter.childNodes.forEach(function(node) {
			if ( Node.TEXT_NODE === node.nodeType ) {
				// Store the text content.
				buttonText = node.textContent.trim();
				// Remove the text node.
				node.textContent = '';
			}
		});

		// Add a loading icon or text while waiting for the response.
		event.submitter.insertAdjacentHTML( 'afterbegin', '<span class="pm-loading-wrap"><svg class="pm-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Pro 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2024 Fonticons, Inc.--><path class="fa-secondary" opacity=".4" d="M478.7 364.6zm-22 6.1l-27.8-15.9a15.9 15.9 0 0 1 -6.9-19.2A184 184 0 1 1 256 72c5.9 0 11.7 .3 17.5 .8-.7-.1-1.5-.2-2.2-.2-8.5-.7-15.2-7.3-15.2-15.8v-32a16 16 0 0 1 15.3-16C266.2 8.5 261.2 8 256 8 119 8 8 119 8 256s111 248 248 248c98 0 182.4-57 222.7-139.4-4.1 7.9-14.2 10.6-22 6.1z"/><path class="fa-primary" d="M271.2 72.6c-8.5-.7-15.2-7.3-15.2-15.8V24.7c0-9.1 7.7-16.8 16.8-16.2C401.9 17.2 504 124.7 504 256a246 246 0 0 1 -25 108.2c-4 8.2-14.4 11-22.3 6.5l-27.8-15.9c-7.4-4.2-9.8-13.4-6.2-21.1A182.5 182.5 0 0 0 440 256c0-96.5-74.3-175.6-168.8-183.4z"/></svg> Saving...</span>' );

		// Send the form data to the server using Fetch API.
		fetch( form.getAttribute('action'), {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		// .then( response => response.json() ) // Assuming the server returns JSON
		.then( data => {
			// Remove the loading icon.
			event.submitter.querySelector('.pm-loading-wrap').remove();

			// Restore original button text.
			event.submitter.insertAdjacentHTML( 'afterbegin', buttonText );

			// Enable the button.
			event.submitter.disabled = false;

			// If successful, add the selected element back.
			if ( 200 ===  data.status ) {
				event.submitter.insertAdjacentHTML( 'beforeend', maiAskNewsVars.selected );
			}
		})
		.catch( error => {
			console.error('Error:', error);

			// Remove the loading icon.
			event.submitter.querySelector('.pm-loading-wrap').remove();

			// Restore original button text.
			event.submitter.insertAdjacentHTML( 'afterbegin', buttonText );

			// Enable the button.
			event.submitter.disabled = false;
		});
	});
});