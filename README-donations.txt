SANJEEVINI SPARSH YOGA – DONATION SYSTEM (HTML version, NO WordPress)
=====================================================================

This is the complete "ssy-donations" WordPress plugin converted to a normal
HTML website folder. Everything the plugin did is here:

  donations/index.html          Donate page (Monthly AutoPay + One-time)
  donations/my-donations.html   Donor self-service: history, receipts, statements
  donations/receipt.html        Printable donation receipt (80G ready)
  donations/manage.html         Donor's AutoPay page – status, payments, CANCEL
  donations/statement.html      Annual statement per financial year (for income tax)
  donations/admin/              Admin dashboard: stats, donors, payments, Excel export
  donations/api/config.php      <-- ALL SETTINGS (Razorpay keys, org details, amounts, email)
  donations/api/*.php           Small PHP files that talk to Razorpay, save donors,
                                send receipt emails and receive webhooks
  donations/api/data/           Database file is created here automatically

Why a few .php files? Payments need the Razorpay SECRET key, which must never be
inside an HTML page (anyone could read it). The PHP files keep it on the server.
All normal hosting (Hostinger, GoDaddy, cPanel, BigRock…) runs PHP – nothing to install,
no WordPress, no database setup.


---------------------------------------------------------------------
1) OPEN IN VS CODE
---------------------------------------------------------------------
  - Unzip. In VS Code: File > Open Folder > choose the unzipped folder.
  - To SEE the pages: install the "Live Server" extension, right-click
    donations/index.html > "Open with Live Server".
    (Menu links like Home / About open the live website, so they always work.)
  - Payments, receipts and admin need PHP. To test them on your computer:
      install PHP (https://windows.php.net/download  – or  brew install php  on Mac),
      then in VS Code's terminal run:   php -S localhost:8000
      and open  http://localhost:8000/donations/
    Or simply upload to the hosting (step 3) and test there.


---------------------------------------------------------------------
2) EDIT SETTINGS  –  donations/api/config.php
---------------------------------------------------------------------
  - key_id / key_secret : Razorpay Dashboard > Account & Settings > API Keys
                          (start with TEST keys rzp_test_..., switch to LIVE later)
  - webhook_secret      : make up a strong password (used in step 4)
  - admin_password      : CHANGE IT – used to log in to /donations/admin/
  - admin_email         : where "new donor / payment failed" alerts go
  - org_address, org_pan, org_reg_no, signatory : printed on receipts
  - g80_enabled + g80_number : turn on if you have 80G (form then asks PAN + address)
  - smtp_host / smtp_user / smtp_pass : an email account from your hosting
                          (e.g. donations@sanjeevinisparshyoga.com) so receipts reach inbox
  - Amounts, monthly/one-time options, impact texts are also in this file.
  Page texts (heading, mission, causes) are plain HTML in donations/index.html.


---------------------------------------------------------------------
3) UPLOAD
---------------------------------------------------------------------
  Upload the whole "donations" folder into the website root (public_html),
  next to index.html, about_us.html etc. (cPanel File Manager or FTP).

  Live addresses:
    https://www.sanjeevinisparshyoga.com/donations
    https://www.sanjeevinisparshyoga.com/donations/my-donations.html
    https://www.sanjeevinisparshyoga.com/donations/admin/

  Make sure the folder donations/api/data/ is writable (permission 755 or 775).


---------------------------------------------------------------------
4) RAZORPAY WEBHOOK (required – this is how monthly debits get recorded)
---------------------------------------------------------------------
  Razorpay Dashboard > Account & Settings > Webhooks > Add New Webhook
    URL:     https://www.sanjeevinisparshyoga.com/donations/api/webhook.php
    Secret:  same as webhook_secret in config.php
    Events:  subscription.authenticated, subscription.activated, subscription.charged,
             subscription.pending, subscription.halted, subscription.cancelled,
             subscription.completed, subscription.paused, subscription.resumed, order.paid
  Do this once in Test mode and again in Live mode.
  Also: Subscriptions / UPI AutoPay must be enabled on the Razorpay account.


---------------------------------------------------------------------
5) LINK FROM THE MAIN WEBSITE
---------------------------------------------------------------------
  In the menu of every .html page, before "Contact Us", add:
      <li><a href="donations/">Donate</a></li>
  (Optional banner / floating button: donate-integration-snippets.html)


---------------------------------------------------------------------
6) TEST, THEN GO LIVE
---------------------------------------------------------------------
  - Log in to /donations/admin/ – the checklist shows what is still missing.
    Use "Test Razorpay connection" and "Send test email".
  - With TEST keys, make one monthly and one one-time donation.
    Check: receipt email, Manage AutoPay link, My donations page, admin dashboard.
  - Replace key_id / key_secret with LIVE keys, add the Live webhook. Done.

  BACKUP: donations/api/data/donations.sqlite is your donor database – download
  a copy now and then. Everything is also in Razorpay Dashboard.
