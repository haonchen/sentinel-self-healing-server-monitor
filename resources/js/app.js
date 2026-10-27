const refreshButton = document.querySelector('#refresh-button');
const serviceDialog = document.querySelector('#service-dialog');
const serviceForm = document.querySelector('#service-form');
const chatDialog = document.querySelector('#chat-dialog');
const chatForm = document.querySelector('#chat-form');
const chatInput = document.querySelector('#chat-input');
const chatTranscript = document.querySelector('#chat-transcript');
const chatHistory = [];

const timeGreeting = document.querySelector('#time-greeting');
const serviceDialogTitle = document.querySelector('#service-dialog-title');
const serviceDialogDescription = document.querySelector('#service-dialog-description');
const serviceIdField = serviceForm?.querySelector('input[name="service_id"]');
const serviceSubmitButton = serviceForm?.querySelector('button[type="submit"]');
const serviceSubmitLabel = serviceSubmitButton?.querySelector('.submit-label');

function updateTimeGreeting() {
	if (!timeGreeting) return;

	const hour = new Date().getHours();
	timeGreeting.textContent = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
}

updateTimeGreeting();
if (timeGreeting) window.setInterval(updateTimeGreeting, 60_000);

function setAddServiceMode() {
	if (!serviceForm || !serviceDialog) return;

	serviceForm.reset();
	serviceForm.action = serviceForm.dataset.storeAction;
	serviceForm.querySelector('input[name="_method"]')?.remove();
	serviceIdField.value = '';
	serviceForm.querySelector('input[name="name"]').value = '';
	serviceForm.querySelector('input[name="container_name"]').value = '';
	serviceForm.querySelector('input[name="check_type"][value="http"]').checked = true;
	serviceForm.querySelector('input[name="check_target"]').value = '';
	serviceForm.querySelector('input[name="cooldown_seconds"]').value = '300';
	serviceForm.querySelector('input[name="description"]').value = '';
	serviceForm.querySelector('input[name="documentation_url"]').value = '';
	serviceDialogTitle.textContent = 'Add a service';
	serviceDialogDescription.textContent = 'Connect a container and choose how Sentinel checks it.';
	serviceSubmitButton.querySelector('.button-plus').hidden = false;
	serviceSubmitLabel.textContent = 'Add service';
	serviceForm.dataset.pendingLabel = 'Adding service';
	syncCheckTarget();
}

function setConfigureServiceMode(button, preserveValues = false) {
	if (!serviceForm || !serviceDialog) return;

	serviceForm.action = button.dataset.updateUrl;
	serviceIdField.value = button.dataset.serviceId;
	let methodField = serviceForm.querySelector('input[name="_method"]');
	if (!methodField) {
		methodField = document.createElement('input');
		methodField.type = 'hidden';
		methodField.name = '_method';
		serviceForm.append(methodField);
	}
	methodField.value = 'PUT';

	if (!preserveValues) {
		serviceForm.querySelector('input[name="name"]').value = button.dataset.name;
		serviceForm.querySelector('input[name="container_name"]').value = button.dataset.containerName;
		serviceForm.querySelectorAll('input[name="check_type"]').forEach((input) => {
			input.checked = input.value === button.dataset.checkType;
		});
		serviceForm.querySelector('input[name="check_target"]').value = button.dataset.checkTarget;
		serviceForm.querySelector('input[name="cooldown_seconds"]').value = button.dataset.cooldownSeconds;
		serviceForm.querySelector('input[name="description"]').value = button.dataset.description;
		serviceForm.querySelector('input[name="documentation_url"]').value = button.dataset.documentationUrl;
	}

	serviceDialogTitle.textContent = 'Configure service';
	serviceDialogDescription.textContent = 'Update the container and how Sentinel checks it.';
	serviceSubmitButton.querySelector('.button-plus').hidden = true;
	serviceSubmitLabel.textContent = 'Save changes';
	serviceForm.dataset.pendingLabel = 'Saving changes';
	syncCheckTarget();
}

const editingServiceId = serviceIdField?.value;
const editingServiceButton = [...document.querySelectorAll('[data-configure-service]')]
	.find((button) => button.dataset.serviceId === editingServiceId);

if (serviceForm) serviceForm.dataset.pendingLabel = 'Adding service';

if (serviceForm?.querySelector('input[name="_method"]')?.value === 'PUT' && editingServiceButton) {
	setConfigureServiceMode(editingServiceButton, true);
}

if (serviceDialog?.open) {
	serviceDialog.close();
	serviceDialog.showModal();
}

document.querySelectorAll('[data-open-service-dialog]').forEach((button) => {
	button.addEventListener('click', () => {
		setAddServiceMode();
		serviceDialog?.showModal();
		serviceDialog?.querySelector('input[name="name"]')?.focus();
	});
});

document.querySelectorAll('[data-configure-service]').forEach((button) => {
	button.addEventListener('click', () => {
		setConfigureServiceMode(button);
		button.closest('details').open = false;
		serviceDialog?.showModal();
		serviceDialog?.querySelector('input[name="name"]')?.focus();
	});
});

document.querySelectorAll('[data-close-service-dialog]').forEach((button) => {
	button.addEventListener('click', () => serviceDialog?.close());
});

serviceDialog?.addEventListener('click', (event) => {
	if (event.target === serviceDialog) serviceDialog.close();
});

function syncCheckTarget() {
	const selectedType = document.querySelector('input[name="check_type"]:checked')?.value;
	if (!selectedType) return;

	const isTcp = selectedType === 'tcp';
	const targetInput = document.querySelector('#check-target');
	document.querySelector('#target-label').textContent = isTcp ? 'Host and port' : 'Health-check URL';
	document.querySelector('#target-help').textContent = isTcp
		? 'Use a hostname and port reachable from the monitor, for example cache:6379.'
		: 'Use an HTTP or HTTPS endpoint reachable from the monitor.';
	targetInput.placeholder = isTcp ? 'cache:6379' : 'https://payments.internal/health';
}

document.querySelectorAll('input[name="check_type"]').forEach((input) => {
	input.addEventListener('change', syncCheckTarget);
});
syncCheckTarget();

serviceForm?.addEventListener('submit', () => {
	const submitButton = serviceForm.querySelector('button[type="submit"]');
	submitButton.disabled = true;
	const spinner = document.createElement('span');
	spinner.className = 'submit-spinner';
	const label = document.createElement('span');
	label.textContent = serviceForm.dataset.pendingLabel;
	submitButton.replaceChildren(spinner, label);
});

function appendChatMessage(role, content) {
	const message = document.createElement('div');
	message.className = `chat-message ${role}-message`;

	if (role === 'assistant') {
		const avatar = document.createElement('span');
		avatar.className = 'message-avatar';
		avatar.textContent = 'S';
		message.append(avatar);
	}

	const text = document.createElement('p');
	text.textContent = content;
	message.append(text);
	chatTranscript.append(message);
	chatTranscript.scrollTop = chatTranscript.scrollHeight;
	return message;
}

async function waitForChatResponse(messages) {
	const response = await fetch('/ollama/chat', {
		method: 'POST',
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
		},
		body: JSON.stringify({ messages }),
	});
	const request = await response.json();
	if (!response.ok) throw new Error(request.message || 'The local assistant could not respond.');
	if (request.status !== 'pending' || typeof request.status_url !== 'string') {
		throw new Error('Sentinel did not start the assistant request. Please try again.');
	}

	const deadline = Date.now() + 180_000;
	while (Date.now() < deadline) {
		await new Promise((resolve) => window.setTimeout(resolve, 1000));

		let statusResponse;
		try {
			statusResponse = await fetch(request.status_url, { headers: { Accept: 'application/json' } });
		} catch {
			continue;
		}

		if (statusResponse.status >= 500) continue;

		const result = await statusResponse.json().catch(() => null);
		if (!result) continue;
		if (statusResponse.status === 202 && ['pending', 'processing'].includes(result.status)) continue;
		if (!statusResponse.ok) throw new Error(result.message || 'The local assistant could not respond.');
		if (result.status !== 'completed' || typeof result.message !== 'string') {
			throw new Error('Sentinel returned an invalid assistant response.');
		}

		return result;
	}

	throw new Error('The local assistant is taking longer than expected. Please try again shortly.');
}

async function sendChatMessage(content) {
	const message = content.trim();
	if (!message || chatForm.dataset.sending === 'true') return;

	chatForm.dataset.sending = 'true';
	document.querySelector('#chat-suggestions').hidden = true;
	chatHistory.push({ role: 'user', content: message });
	appendChatMessage('user', message);
	chatInput.value = '';
	chatInput.style.height = 'auto';
	const typing = appendChatMessage('assistant', 'Thinking…');
	typing.classList.add('typing-message');
	const sendButton = chatForm.querySelector('.chat-send');
	sendButton.disabled = true;

	try {
		const result = await waitForChatResponse(chatHistory.slice(-12));

		typing.remove();
		chatHistory.push({ role: 'assistant', content: result.message });
		appendChatMessage('assistant', result.message);
	} catch (error) {
		typing.remove();
		const message = error instanceof TypeError
			? 'Sentinel is temporarily unreachable. Please retry in a moment.'
			: error.message || 'The local assistant could not respond. Please try again.';
		const failure = appendChatMessage('error', message);
		failure.setAttribute('role', 'alert');
	} finally {
		chatForm.dataset.sending = 'false';
		sendButton.disabled = false;
		chatInput.focus();
	}
}

document.querySelector('#open-chat')?.addEventListener('click', () => {
	chatDialog?.showModal();
	chatInput?.focus();
});
document.querySelector('#close-chat')?.addEventListener('click', () => chatDialog?.close());
chatDialog?.addEventListener('click', (event) => {
	if (event.target === chatDialog) chatDialog.close();
});
chatForm?.addEventListener('submit', (event) => {
	event.preventDefault();
	void sendChatMessage(chatInput.value);
});
chatInput?.addEventListener('keydown', (event) => {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault();
		chatForm.requestSubmit();
	}
});
chatInput?.addEventListener('input', () => {
	chatInput.style.height = 'auto';
	chatInput.style.height = `${Math.min(chatInput.scrollHeight, 120)}px`;
});
document.querySelectorAll('[data-chat-prompt]').forEach((button) => {
	button.addEventListener('click', () => void sendChatMessage(button.dataset.chatPrompt));
});

async function refreshSnapshot() {
	try {
		const response = await fetch('/dashboard/snapshot', { headers: { Accept: 'application/json' } });
		if (!response.ok) return;

		const snapshot = await response.json();
		document.querySelector('#healthy-count').textContent = snapshot.healthy;
		document.querySelector('#degraded-count').textContent = snapshot.degraded;
		document.querySelector('#down-count').textContent = snapshot.down;
		document.querySelector('#last-refresh').textContent = 'Updated just now';
		document.querySelector('#activity-feed').innerHTML = snapshot.activity_html;
		document.querySelector('#activity-count').textContent = `${snapshot.activity_count} recent checks`;
		document.querySelector('#incident-content').innerHTML = snapshot.incidents_html;
		document.querySelector('#incident-open-count').textContent = `${snapshot.open_incidents} open`;

		const incidentNavigationCount = document.querySelector('#open-incident-nav');
		incidentNavigationCount.textContent = snapshot.open_incidents;
		incidentNavigationCount.hidden = snapshot.open_incidents === 0;

		snapshot.services.forEach((service) => {
			const row = document.querySelector(`[data-service-id="${service.id}"]`);
			if (!row) return;

			row.querySelector('.status-text').textContent = service.status[0].toUpperCase() + service.status.slice(1);
			row.querySelector('.status-dot').className = `status-dot ${service.status}`;
			row.querySelector('.check-cell').textContent = service.last_checked_at ?? 'Not checked yet';
		});

		document.querySelector('#overall-status').textContent = snapshot.overall_status;
	} catch {
		document.querySelector('#last-refresh').textContent = 'Connection unavailable';
	}
}

refreshButton?.addEventListener('click', refreshSnapshot);
if (document.querySelector('#refresh-button')) window.setInterval(refreshSnapshot, 10000);

document.querySelectorAll('.chaos-form').forEach((form) => {
	form.addEventListener('submit', (event) => {
		if (!window.confirm('Stop this container for a chaos test? Sentinel will attempt an automatic restart.')) {
			event.preventDefault();
		}
	});
});

document.querySelectorAll('.remove-service-form').forEach((form) => {
	form.addEventListener('submit', (event) => {
		const serviceName = form.dataset.serviceName;
		if (!window.confirm(`Remove ${serviceName}? This permanently deletes its health checks and incident history.`)) {
			event.preventDefault();
		}
	});
});
