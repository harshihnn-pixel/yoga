/* Donor pages: receipt.html, manage.html, my-donations.html, statement.html */
(function () {
	'use strict';
	var API = 'api/donor.php';
	var sheet = document.getElementById('sheet');
	var view = document.body.getAttribute('data-view');
	var qs = new URLSearchParams(location.search);

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function get(params) {
		return fetch(API + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin' }).then(json);
	}
	function post(params, body) {
		var fd = new FormData();
		Object.keys(body || {}).forEach(function (k) { fd.append(k, body[k]); });
		return fetch(API + '?' + new URLSearchParams(params).toString(), { method: 'POST', body: fd, credentials: 'same-origin' }).then(json);
	}
	function json(r) { return r.json().catch(function () { return { success: false, data: { message: 'The server is not reachable. These pages work once uploaded to hosting with PHP.' } }; }); }

	function head(org) {
		var h = '<header class="ssyad-head">';
		if (org && org.logo) { h += '<img src="' + esc(org.logo) + '" alt="" class="ssyad-logo">'; }
		h += '<div><div class="ssyad-org">' + esc(org ? org.name : 'Sanjeevini Sparsh Yoga') + '</div>';
		if (org && org.address) { h += '<div class="ssyad-muted">' + esc(org.address).replace(/\n/g, '<br>') + '</div>'; }
		return h + '</div></header>';
	}
	var FOOT = '<footer class="ssyad-foot no-print"><a href="https://www.sanjeevinisparshyoga.com/">Back to website</a> · <a href="./">Donate</a></footer>';
	function paint(org, body) {
		if (org && org.color) { document.documentElement.style.setProperty('--ssyad-color', org.color); }
		sheet.innerHTML = head(org) + body + FOOT;
	}
	function kv(rows) {
		return '<table class="ssyad-kv">' + rows.map(function (r) { return '<tr><th>' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>'; }).join('') + '</table>';
	}
	function ids(org) {
		var a = [];
		if (org.reg_no) { a.push('Reg. no.: ' + esc(org.reg_no)); }
		if (org.pan) { a.push('PAN: ' + esc(org.pan)); }
		if (org.g80_enabled && org.g80_number) { a.push('80G: ' + esc(org.g80_number)); }
		return a.length ? '<p class="ssyad-ids">' + a.join(' · ') + '</p>' : '';
	}
	function g80(org) {
		if (!org.g80_enabled) { return ''; }
		return '<div class="ssyad-80g"><strong>Section 80G</strong><br>' + esc(org.g80_text) + (org.g80_number ? '<br>80G registration no.: ' + esc(org.g80_number) : '') + '</div>';
	}
	function signature(org) {
		if (!org.signatory) { return ''; }
		return '<div class="ssyad-sign"><div class="ssyad-sign-line"></div>' + esc(org.signatory) + '<br><span class="ssyad-muted">Authorised signatory, ' + esc(org.name) + '</span></div>';
	}
	function payTable(pays) {
		var h = '<table class="ssyad-list"><thead><tr><th>Date</th><th>Amount</th><th>Type</th><th>Receipt</th></tr></thead><tbody>';
		pays.forEach(function (p) {
			h += '<tr><td>' + esc(p.date) + '</td><td>' + esc(p.amount) + '</td><td>' + esc(p.type) + '</td><td><a href="' + esc(p.receipt_url) + '" target="_blank">' + esc(p.receipt_no) + '</a></td></tr>';
		});
		return h + '</tbody></table>';
	}
	var PRINT = '<div class="ssyad-actions no-print"><button onclick="window.print()" class="ssyad-button">Print / Save as PDF</button></div>';

	function portalForm(notice) {
		return '<form class="ssyad-portal-form" id="portal-form"><h2>View my donations</h2>' +
			(notice ? '<div class="ssyad-alert">' + esc(notice) + '</div>' : '') +
			'<p class="ssyad-muted">Enter the email you donated with. We will send you a secure link to see your donations, download receipts and annual statements, or manage your AutoPay.</p>' +
			'<label class="ssyad-field"><span>Email</span><input type="email" name="email" required></label>' +
			'<div class="ssyad-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>' +
			'<button type="submit" class="ssyad-button ssyad-btn">Email me a link</button></form>';
	}
	function bindPortalForm() {
		var f = document.getElementById('portal-form');
		if (!f) { return; }
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var b = f.querySelector('button'); b.disabled = true; b.textContent = 'Please wait…';
			post({ action: 'portal_request' }, { email: f.email.value, website: f.website.value }).then(function (res) {
				if (!res.success) { b.disabled = false; b.textContent = 'Email me a link'; alertBox(f, res.data.message, true); return; }
				paint(null, '<h1>Check your email</h1><p>' + esc(res.data.message) + '</p>');
			});
		});
	}
	function alertBox(where, msg, err) {
		var d = document.createElement('div');
		d.className = 'ssyad-alert' + (err ? ' ssyad-alert-err' : '');
		d.textContent = msg;
		where.insertBefore(d, where.firstChild.nextSibling);
	}
	function notFound(msg) {
		paint(null, '<h1>' + esc(msg || 'This link is invalid or has expired') + '</h1>' + portalForm());
		bindPortalForm();
	}

	/* ---------- Views ---------- */
	var views = {
		receipt: function () {
			get({ action: 'receipt', p: qs.get('p') || '', k: qs.get('k') || '' }).then(function (res) {
				if (!res.success) { return notFound(res.data.message); }
				var d = res.data, o = d.org;
				document.title = 'Donation receipt ' + d.receipt_no + ' – ' + o.name;
				paint(o, PRINT + '<h1 class="ssyad-doc-title">Donation Receipt</h1>' + ids(o) + kv(d.rows) + g80(o) + signature(o) +
					'<p class="ssyad-muted ssyad-small">This is a computer-generated receipt for an online donation received through Razorpay.</p>');
			});
		},

		manage: function (msgRes) {
			var t = qs.get('t') || '';
			var p = msgRes ? Promise.resolve(msgRes) : get({ action: 'manage', t: t });
			p.then(function (res) {
				if (!res.success) { return notFound(res.data.message); }
				var d = res.data, s = d.sub;
				var h = '<h1>Your AutoPay donation</h1>';
				if (d.message) { h += '<div class="ssyad-alert">' + esc(d.message) + '</div>'; }
				if (d.error) { h += '<div class="ssyad-alert ssyad-alert-err">' + esc(d.error) + '</div>'; }
				h += '<div class="ssyad-status ssyad-status-' + esc(s.status) + '">' + esc(s.status_text) + '</div>';
				var rows = [['Donor', s.name], ['Amount', s.amount_text], ['Payments made', s.paid], ['Started on', s.started]];
				if (s.next) { rows.push(['Next debit', s.next]); }
				if (s.campaign) { rows.push(['Towards', s.campaign]); }
				h += kv(rows);
				if (s.approve_url) { h += '<p><a class="ssyad-button" href="' + esc(s.approve_url) + '">Approve AutoPay now</a></p>'; }
				if (s.restart_url) { h += '<p><a class="ssyad-button" href="' + esc(s.restart_url) + '">Restart my AutoPay</a></p>'; }
				if (d.payments.length) { h += '<h2>Payments</h2>' + payTable(d.payments); }
				if (s.can_cancel) {
					h += '<form class="ssyad-cancel" id="cancel-form"><h2>Stop your AutoPay</h2><p class="ssyad-muted">You can cancel anytime. Past donations are not affected. You can also revoke the mandate from your UPI app (AutoPay section) or card issuer.</p><button type="submit" class="ssyad-button ssyad-button-danger">Cancel AutoPay</button></form>';
				}
				h += '<p class="ssyad-small"><a href="' + esc(d.portal_url) + '">See all my donations and annual statements →</a></p>';
				paint(d.org, h);
				var f = document.getElementById('cancel-form');
				if (f) {
					f.addEventListener('submit', function (e) {
						e.preventDefault();
						if (!window.confirm('Cancel this AutoPay? No further amounts will be debited.')) { return; }
						var b = f.querySelector('button'); b.disabled = true; b.textContent = 'Cancelling…';
						post({ action: 'cancel', t: t }, {}).then(function (r) {
							if (!r.success) { b.disabled = false; b.textContent = 'Cancel AutoPay'; window.alert(r.data.message); return; }
							views.manage(r);
						});
					});
				}
			});
		},

		portal: function () {
			var e = qs.get('e');
			if (!e) { paint(null, portalForm()); bindPortalForm(); return; }
			get({ action: 'portal', e: e, x: qs.get('x') || '', k: qs.get('k') || '' }).then(function (res) {
				if (!res.success) { return notFound(res.data.message); }
				var d = res.data;
				var h = '<h1>My donations</h1><p class="ssyad-muted">' + esc(d.email) + '</p>';
				h += '<div class="ssyad-cards"><div><span>Total donated</span><strong>' + esc(d.total) + '</strong></div><div><span>Donations</span><strong>' + esc(d.count) + '</strong></div></div>';
				if (d.subs.length) {
					h += '<h2>AutoPay</h2><table class="ssyad-list"><thead><tr><th>Amount</th><th>Status</th><th>Next debit</th><th></th></tr></thead><tbody>';
					d.subs.forEach(function (s) {
						h += '<tr><td>' + esc(s.amount + ' ' + s.freq) + '</td><td>' + esc(s.status_text) + '</td><td>' + esc(s.next || '—') + '</td><td><a href="' + esc(s.manage_url) + '">Manage</a></td></tr>';
					});
					h += '</tbody></table>';
				}
				if (d.payments.length) {
					h += '<h2>Annual statements (for income tax)</h2><p>';
					d.statements.forEach(function (s) { h += '<a class="ssyad-pill" target="_blank" href="' + esc(s.url) + '">' + esc(s.label) + '</a> '; });
					h += '</p><h2>All donations</h2>' + payTable(d.payments);
				} else {
					h += '<p>No completed donations yet.</p>';
				}
				paint(null, h);
			});
		},

		statement: function () {
			get({ action: 'statement', e: qs.get('e') || '', fy: qs.get('fy') || '', x: qs.get('x') || '', k: qs.get('k') || '' }).then(function (res) {
				if (!res.success) { return notFound(res.data.message); }
				var d = res.data, o = d.org;
				document.title = 'Annual statement ' + d.fy + ' – ' + o.name;
				var h = PRINT + '<h1 class="ssyad-doc-title">Annual Donation Statement – ' + esc(d.fy) + '</h1>' + ids(o) + kv(d.rows);
				h += '<table class="ssyad-list"><thead><tr><th>#</th><th>Date</th><th>Receipt no.</th><th>Mode</th><th>Transaction ID</th><th style="text-align:right">Amount</th></tr></thead><tbody>';
				d.payments.forEach(function (p, i) {
					h += '<tr><td>' + (i + 1) + '</td><td>' + esc(p.date) + '</td><td>' + esc(p.receipt_no) + '</td><td>' + esc(p.method) + '</td><td class="ssyad-small">' + esc(p.payment_id) + '</td><td style="text-align:right">' + esc(p.amount) + '</td></tr>';
				});
				h += '<tr class="ssyad-total"><td colspan="5">Total</td><td style="text-align:right">' + esc(d.total) + '</td></tr></tbody></table>';
				h += g80(o) + signature(o) + '<p class="ssyad-muted ssyad-small">Computer-generated statement of online donations received through Razorpay.</p>';
				paint(o, h);
			});
		}
	};

	if (views[view]) { views[view](); }
})();
