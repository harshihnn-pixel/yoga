/* Sanjeevini Sparsh Yoga – donation form (HTML version of the [ssy_donate] shortcode).
 * Settings come from api/settings.php (edit api/config.php). */
(function () {
	'use strict';

	var API = (document.querySelector('meta[name="ssy-api"]') || {}).content || 'api/';

	var T = {
		setup: 'Set up {amount}{suffix} AutoPay',
		donate: 'Donate {amount}',
		note: '{amount} will be auto-debited {word} from your UPI app, debit card or credit card – only after you approve the AutoPay mandate.{duration} You will get a reminder before every debit. Cancel anytime.',
		note_once: 'This is a one-time donation. Nothing will be debited again.',
		duration: ' Runs {duration}.',
		processing: 'Please wait…',
		verifying: 'Confirming your donation…',
		closed: 'The payment window was closed. Nothing was debited – you can try again.',
		failed: 'Payment failed: {reason}. Nothing was debited – please try again or use another method.',
		network: 'Network error. Please check your connection and try again.',
		no_server: 'The payment server is not reachable. (If you are previewing in VS Code, payments only work once the site is uploaded to hosting with PHP, or run with "php -S".)',
		no_keys: 'Online donations are being set up. Please check back soon.',
		no_checkout: 'Could not load the Razorpay payment window. Please disable ad-blockers and try again.',
		min: 'Please enter at least {amount}.',
		max: 'Please enter at most {amount}.',
		name: 'Please enter your name.',
		email: 'Please enter a valid email address.',
		phone: 'Please enter a valid 10-digit mobile number.',
		pan: 'Please enter a valid PAN (e.g. ABCDE1234F) or leave it empty.',
		consent: 'Please accept the AutoPay authorisation.',
		thanks: 'Thank you, {name}!',
		thanks_sub: 'Your {amount}{suffix} AutoPay is active. A receipt has been emailed to {email}. Your bank or UPI app will remind you before every debit.',
		thanks_once: 'We have received your donation of {amount}. A receipt has been emailed to {email}.',
		thanks_wait: 'Your AutoPay is approved. Your receipt will be emailed to {email} in a few minutes.',
		receipt: 'Download receipt',
		manage: 'Manage AutoPay',
		share: 'Share on WhatsApp'
	};

	// Used only if api/settings.php can't be reached (e.g. previewing without PHP).
	var DEFAULTS = {
		ready: false, offline: true, title: 'Make your sacred offering',
		freqs: [{ key: 'monthly', label: 'Monthly', suffix: '/month', word: 'every month' }, { key: 'onetime', label: 'One-time', suffix: '', word: '' }],
		amounts: [100, 500, 1000, 2000, 5000], default: 1000,
		impacts: { 100: 'Supports yoga and meditation for someone seeking inner peace', 500: 'Helps share spiritual education and awareness with more seekers', 1000: 'Supports yoga and awareness activities for children and youth', 2000: 'Extends yoga, meditation and sadhana to those without access', 5000: 'Sustains our mission of service to society for a whole month' },
		min: 100, max: 15000, maxOnce: 500000,
		durations: [{ value: 1, label: 'For 1 year', phrase: 'for 1 year' }, { value: 3, label: 'For 3 years', phrase: 'for 3 years' }, { value: 0, label: 'Until I cancel', phrase: 'until you cancel' }],
		duration: 0, thankyou: '', share: '', consent: false, consentText: '', pan: false, address: false,
		dedication: true, anonymous: true, color: '#7c5cc4'
	};

	function inr(n) { return '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
	function fmt(str, vars) {
		return String(str || '').replace(/\{(\w+)\}/g, function (m, k) { return Object.prototype.hasOwnProperty.call(vars, k) ? vars[k] : m; });
	}
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function el(tag, attrs, text) {
		var e = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
		if (text) { e.textContent = text; }
		return e;
	}
	function iconLink(href, cls, icon, text) {
		var a = el('a', { href: href, 'class': cls, target: '_blank', rel: 'noopener' });
		a.appendChild(el('i', { 'class': (icon.indexOf('fa-brands') === 0 ? icon : 'fa ' + icon), 'aria-hidden': 'true' }));
		a.appendChild(document.createTextNode(' ' + text));
		return a;
	}
	function post(action, data) {
		var body = new FormData();
		Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		return fetch(API + 'checkout.php?action=' + action, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { success: false, data: { message: T.no_server } }; }); });
	}
	function track(cfg, amount, freq) {
		try {
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({ event: 'donation_complete', donation_value: Number(amount), donation_currency: 'INR', donation_frequency: freq, donation_campaign: cfg.campaign || '' });
			if (typeof window.gtag === 'function') { window.gtag('event', 'purchase', { value: Number(amount), currency: 'INR', items: [{ item_name: 'Donation ' + freq }] }); }
			if (typeof window.fbq === 'function') { window.fbq('track', 'Donate', { value: Number(amount), currency: 'INR' }); }
		} catch (e) { /* ignore */ }
	}

	/* Build the form markup (same structure/classes as the WordPress plugin) */
	function render(root, cfg) {
		var hasRecur = cfg.freqs.some(function (f) { return f.key !== 'onetime'; });
		var h = '<form class="ssyad-form" novalidate>';
		if (cfg.title) { h += '<h3 class="ssyad-title">' + esc(cfg.title) + '</h3>'; }
		if (cfg.freqs.length > 1) {
			h += '<div class="ssyad-freq" role="radiogroup" aria-label="Donation frequency">';
			cfg.freqs.forEach(function (f, i) {
				h += '<button type="button" role="radio" class="ssyad-freq-btn' + (i === 0 ? ' is-active' : '') + '" aria-checked="' + (i === 0) + '" data-freq="' + esc(f.key) + '">' + esc(f.label) + '</button>';
			});
			h += '</div>';
		} else if (hasRecur) {
			h += '<div class="ssyad-badge"><i class="fa fa-rotate" aria-hidden="true"></i> ' + esc(cfg.freqs[0].label) + ' AutoPay donation</div>';
		}
		h += '<div class="ssyad-amounts">';
		cfg.amounts.forEach(function (a) {
			h += '<button type="button" class="ssyad-chip' + (a === cfg.default ? ' is-active' : '') + '" data-amount="' + a + '">' + inr(a) + '</button>';
		});
		var inList = cfg.amounts.indexOf(cfg.default) > -1;
		h += '<label class="ssyad-custom"><span class="ssyad-cur">₹</span><input type="number" inputmode="numeric" min="1" step="1" name="custom_amount" placeholder="Other amount" aria-label="Other amount in rupees"' + (inList ? '' : ' value="' + esc(cfg.default) + '"') + '></label></div>';
		h += '<p class="ssyad-impact" hidden></p>';
		if (cfg.durations.length > 1 && hasRecur) {
			h += '<label class="ssyad-field ssyad-duration"><span>How long?</span><select name="duration">';
			cfg.durations.forEach(function (d) { h += '<option value="' + d.value + '"' + (d.value === cfg.duration ? ' selected' : '') + '>' + esc(d.label) + '</option>'; });
			h += '</select></label>';
		} else {
			h += '<input type="hidden" name="duration" value="' + esc(cfg.duration) + '">';
		}
		h += '<div class="ssyad-grid">';
		h += '<label class="ssyad-field ssyad-full"><span>Full name *</span><input type="text" name="name" autocomplete="name" maxlength="100" required></label>';
		h += '<label class="ssyad-field"><span>Email *</span><input type="email" name="email" autocomplete="email" maxlength="150" required></label>';
		h += '<label class="ssyad-field"><span>Mobile number *</span><input type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="17" placeholder="98XXXXXXXX" required></label>';
		if (cfg.pan) { h += '<label class="ssyad-field"><span>PAN (for 80G receipt)</span><input type="text" name="pan" maxlength="10" autocapitalize="characters" placeholder="ABCDE1234F"></label>'; }
		if (cfg.address) { h += '<label class="ssyad-field' + (cfg.pan ? '' : ' ssyad-full') + '"><span>Address (for 80G receipt)</span><input type="text" name="address" maxlength="250" autocomplete="street-address"></label>'; }
		h += '</div>';
		if (cfg.dedication || cfg.anonymous) {
			h += '<details class="ssyad-more"><summary><i class="fa fa-hand-holding-heart" aria-hidden="true"></i> Dedicate this donation / privacy options</summary>';
			if (cfg.dedication) { h += '<label class="ssyad-field"><span>In honour / memory of (optional)</span><input type="text" name="dedication" maxlength="200"></label>'; }
			if (cfg.anonymous) { h += '<label class="ssyad-check"><input type="checkbox" name="anonymous" value="1"> Do not show my name publicly</label>'; }
			h += '</details>';
		}
		h += '<div class="ssyad-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
		h += '<div class="ssyad-note" aria-live="polite"></div>';
		if (cfg.consent) { h += '<label class="ssyad-check ssyad-consent"><input type="checkbox" name="consent" value="1"> ' + esc(cfg.consentText) + '</label>'; }
		h += '<button type="submit" class="ssyad-btn">Donate</button>';
		h += '<p class="ssyad-error" role="alert" hidden></p>';
		h += '<p class="ssyad-secure"><i class="fa fa-lock" aria-hidden="true"></i> Secured by Razorpay · UPI AutoPay (PhonePe, GPay, Paytm) · Debit &amp; credit cards</p>';
		h += '</form><div class="ssyad-success" hidden></div>';
		root.innerHTML = h;
		if (cfg.color) { root.style.setProperty('--ssyad-color', cfg.color); }
	}

	function init(root, cfg) {
		cfg.campaign = root.getAttribute('data-campaign') || '';
		render(root, cfg);

		var form = root.querySelector('form');
		var btn = form.querySelector('.ssyad-btn');
		var note = form.querySelector('.ssyad-note');
		var errBox = form.querySelector('.ssyad-error');
		var impact = form.querySelector('.ssyad-impact');
		var custom = form.querySelector('input[name="custom_amount"]');
		var durWrap = form.querySelector('.ssyad-duration');
		var durSel = form.querySelector('[name="duration"]');
		var chips = form.querySelectorAll('.ssyad-chip');
		var freqBtns = form.querySelectorAll('.ssyad-freq-btn');
		var success = root.querySelector('.ssyad-success');

		var state = { freq: cfg.freqs[0].key, amount: custom && custom.value ? Number(custom.value) : Number(cfg.default || 0), busy: false };

		// Allow donations/#onetime to preselect the one-time tab
		if (location.hash === '#onetime') {
			var ob = form.querySelector('[data-freq="onetime"]');
			if (ob) { setTimeout(function () { ob.click(); }, 0); }
		}

		function freqObj() {
			for (var i = 0; i < cfg.freqs.length; i++) { if (cfg.freqs[i].key === state.freq) { return cfg.freqs[i]; } }
			return cfg.freqs[0];
		}
		function durationLabel() {
			if (!durSel) { return ''; }
			var v = Number(durSel.value);
			for (var i = 0; i < cfg.durations.length; i++) { if (Number(cfg.durations[i].value) === v) { return cfg.durations[i].phrase || ''; } }
			return '';
		}
		function showError(msg) { errBox.textContent = msg; errBox.hidden = !msg; }
		function update() {
			var once = state.freq === 'onetime';
			var f = freqObj();
			var amt = state.amount > 0 ? inr(state.amount) : '₹—';
			var txt = cfg.impacts && cfg.impacts[state.amount];
			impact.textContent = txt || '';
			impact.hidden = !txt;
			if (durWrap) { durWrap.hidden = once; }
			btn.textContent = once ? fmt(T.donate, { amount: amt }) : fmt(T.setup, { amount: amt, suffix: f.suffix });
			if (once) { note.textContent = T.note_once; } else {
				var d = durationLabel();
				note.textContent = fmt(T.note, { amount: amt, word: f.word, duration: d ? fmt(T.duration, { duration: d }) : '' });
			}
			var consent = form.querySelector('.ssyad-consent');
			if (consent) { consent.hidden = once; }
		}

		chips.forEach(function (c) {
			c.addEventListener('click', function () {
				chips.forEach(function (x) { x.classList.remove('is-active'); });
				c.classList.add('is-active');
				if (custom) { custom.value = ''; }
				state.amount = Number(c.getAttribute('data-amount'));
				showError(''); update();
			});
		});
		if (custom) {
			custom.addEventListener('input', function () {
				chips.forEach(function (x) { x.classList.remove('is-active'); });
				state.amount = Number(custom.value) || 0; update();
			});
		}
		freqBtns.forEach(function (b) {
			b.addEventListener('click', function () {
				freqBtns.forEach(function (x) { x.classList.remove('is-active'); x.setAttribute('aria-checked', 'false'); });
				b.classList.add('is-active'); b.setAttribute('aria-checked', 'true');
				state.freq = b.getAttribute('data-freq');
				showError(''); update();
			});
		});
		if (durSel && durSel.tagName === 'SELECT') { durSel.addEventListener('change', update); }

		function val(name) {
			var f = form.querySelector('[name="' + name + '"]');
			if (!f) { return ''; }
			if (f.type === 'checkbox') { return f.checked ? '1' : ''; }
			return String(f.value || '').trim();
		}
		function validate() {
			var once = state.freq === 'onetime';
			var max = once ? cfg.maxOnce : cfg.max;
			if (!state.amount || state.amount < cfg.min) { return fmt(T.min, { amount: inr(cfg.min) }); }
			if (max > 0 && state.amount > max) { return fmt(T.max, { amount: inr(max) }); }
			if (!val('name')) { return T.name; }
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email'))) { return T.email; }
			var phone = val('phone').replace(/\D/g, '').replace(/^(91|0)(?=\d{10}$)/, '');
			if (!/^[6-9]\d{9}$/.test(phone)) { return T.phone; }
			var pan = val('pan').toUpperCase();
			if (pan && !/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(pan)) { return T.pan; }
			if (cfg.consent && !once && !val('consent')) { return T.consent; }
			return '';
		}
		function setBusy(on, label) {
			state.busy = on; btn.disabled = on;
			if (on) { btn.textContent = label || T.processing; } else { update(); }
		}
		function showSuccess(res, d) {
			var once = d.mode === 'order';
			var f = freqObj();
			var name = val('name').split(' ')[0];
			form.hidden = true;
			success.innerHTML = '';
			var tick = el('div', { 'class': 'ssyad-tick', 'aria-hidden': 'true' }); tick.appendChild(el('i', { 'class': 'fa fa-check' })); success.appendChild(tick);
			success.appendChild(el('h3', { 'class': 'ssyad-title' }, fmt(T.thanks, { name: name })));
			var msg = once ? fmt(T.thanks_once, { amount: inr(state.amount), email: val('email') })
				: (res.receipt ? fmt(T.thanks_sub, { amount: inr(state.amount), suffix: f.suffix, email: val('email') }) : fmt(T.thanks_wait, { email: val('email') }));
			success.appendChild(el('p', {}, msg));
			var actions = el('div', { 'class': 'ssyad-actions' });
			if (res.receipt) { actions.appendChild(iconLink(res.receipt, 'ssyad-btn', 'fa-file-arrow-down', T.receipt)); }
			if (res.manage) { actions.appendChild(iconLink(res.manage, 'ssyad-btn ssyad-btn-ghost', 'fa-gear', T.manage)); }
			if (cfg.share) { actions.appendChild(iconLink('https://wa.me/?text=' + encodeURIComponent(cfg.share), 'ssyad-btn ssyad-btn-wa', 'fa-brands fa-whatsapp', T.share)); }
			success.appendChild(actions);
			success.hidden = false;
			root.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
		function verify(r, d) {
			state.verifying = true;
			setBusy(true, T.verifying);
			var payload = { mode: d.mode, razorpay_payment_id: r.razorpay_payment_id || '', razorpay_signature: r.razorpay_signature || '' };
			if (d.mode === 'subscription') { payload.razorpay_subscription_id = r.razorpay_subscription_id || d.subscription_id; }
			else { payload.razorpay_order_id = r.razorpay_order_id || d.order_id; }
			post('verify', payload).then(function (res) {
				if (!res.success) { setBusy(false); showError((res.data && res.data.message) || T.network); return; }
				track(cfg, state.amount, state.freq);
				if (cfg.thankyou) {
					var url = cfg.thankyou + (cfg.thankyou.indexOf('?') > -1 ? '&' : '?') + 'donation=' + encodeURIComponent(state.freq) + '&amount=' + encodeURIComponent(state.amount);
					if (res.data.receipt) { url += '&receipt=' + encodeURIComponent(res.data.receipt); }
					window.location.href = url; return;
				}
				showSuccess(res.data, d);
			}).catch(function () { setBusy(false); showError(T.network); });
		}
		function openCheckout(d) {
			if (typeof window.Razorpay !== 'function') { setBusy(false); showError(T.no_checkout); return; }
			var opts = {
				key: d.key, name: d.org, description: d.description, prefill: d.prefill, notes: d.notes,
				theme: { color: cfg.color },
				handler: function (r) { verify(r, d); },
				modal: { confirm_close: true, ondismiss: function () { if (!state.verifying) { setBusy(false); showError(T.closed); } } }
			};
			if (d.logo) { opts.image = d.logo; }
			if (d.mode === 'subscription') { opts.subscription_id = d.subscription_id; }
			else { opts.order_id = d.order_id; opts.amount = d.amount; opts.currency = 'INR'; }
			var rz = new window.Razorpay(opts);
			rz.on('payment.failed', function (resp) { showError(fmt(T.failed, { reason: (resp && resp.error && resp.error.description) || '' })); });
			rz.open();
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (state.busy) { return; }
			if (cfg.offline) { showError(T.no_server); return; }
			if (!cfg.ready) { showError(T.no_keys); return; }
			var err = validate();
			if (err) { showError(err); return; }
			showError(''); setBusy(true);
			post('create', {
				frequency: state.freq, amount: state.amount, duration: durSel ? durSel.value : '',
				name: val('name'), email: val('email'), phone: val('phone'), pan: val('pan').toUpperCase(),
				address: val('address'), dedication: val('dedication'), anonymous: val('anonymous'),
				consent: val('consent'), campaign: cfg.campaign || '', website: val('website')
			}).then(function (res) {
				if (!res.success) { setBusy(false); showError((res.data && res.data.message) || T.network); return; }
				openCheckout(res.data);
			}).catch(function () { setBusy(false); showError(T.network); });
		});

		update();
	}

	function boot() {
		var roots = document.querySelectorAll('.ssyad[data-donate]');
		if (!roots.length) { return; }
		fetch(API + 'settings.php', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { return (res && res.success) ? res.data : DEFAULTS; })
			.catch(function () { return DEFAULTS; })
			.then(function (cfg) { roots.forEach(function (r) { init(r, JSON.parse(JSON.stringify(cfg))); }); });
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
