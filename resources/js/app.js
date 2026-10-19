const refreshButton = document.querySelector('#refresh-button');
const serviceDialog = document.querySelector('#service-dialog');
const serviceForm = document.querySelector('#service-form');
const chatDialog = document.querySelector('#chat-dialog');
const chatForm = document.querySelector('#chat-form');
const chatInput = document.querySelector('#chat-input');
const chatTranscript = document.querySelector('#chat-transcript');
const chatHistory = [];

const timeGreeting = document.querySelector('#time-greeting');

function updateTimeGreeting() {
	if (!timeGreeting) return;

	const hour = new Date().getHours();
	timeGreeting.textContent = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
}

updateTimeGreeting();
if (timeGreeting) window.setInterval(updateTimeGreeting, 60_000);

if (serviceDialog?.open) {
	serviceDialog.close();
	serviceDialog.showModal();
}

document.querySelectorAll('[data-open-service-dialog]').forEach((button) => {
	button.addEventListener('click', () => {
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
	submitButton.innerHTML = '<span class="submit-spinner"></span>Adding service';
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
		const response = await fetch('/ollama/chat', {
			method: 'POST',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
			},
			body: JSON.stringify({ messages: chatHistory.slice(-12) }),
		});
		const result = await response.json();
		if (!response.ok) throw new Error(result.message || 'The local assistant could not respond.');

		typing.remove();
		chatHistory.push({ role: 'assistant', content: result.message });
		appendChatMessage('assistant', result.message);
	} catch (error) {
		typing.remove();
		const failure = appendChatMessage('error', error.message || 'Ollama is unavailable. Check that it is running locally.');
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
