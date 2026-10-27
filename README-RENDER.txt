TEST HOSTING ON RENDER (via GitHub) – the live website is NOT touched
=====================================================================

0. SECRETS STAY OFF GITHUB
   - Do NOT type keys/passwords into donations/api/config.php.
   - On your computer: copy donations/api/config.local.example.php
     -> name the copy config.local.php -> put your keys there.
     (.gitignore makes sure config.local.php is never uploaded.)
   - On Render you put the keys in "Environment Variables" (step 3).
   - Use Razorpay TEST keys (rzp_test_...) on Render. Live keys = real money.

1. GITHUB
   - github.com > New repository > name: ssy-website > choose PRIVATE > Create.
   - In VS Code: open this folder > Source Control (left bar) >
     "Publish to GitHub" > choose the PRIVATE repository.
   - Before pushing, check the file list does NOT contain config.local.php.

2. RENDER
   - render.com > New + > Web Service > connect your GitHub > pick ssy-website.
   - Language / Runtime: Docker   (Render reads the Dockerfile automatically)
   - Instance type: Free > Create Web Service.

3. RENDER > your service > Environment > Add Environment Variable:
     SSY_KEY_ID          rzp_test_xxxxxxxx
     SSY_KEY_SECRET      (test key secret)
     SSY_ADMIN_PASSWORD  (your admin password)
     SSY_ADMIN_EMAIL     (email for alerts)
     SSY_WEBHOOK_SECRET  (any password you make up – optional for testing)
   Save > Render redeploys.

4. OPEN  https://<your-service>.onrender.com/index.html
         https://<your-service>.onrender.com/donations/
         https://<your-service>.onrender.com/donations/admin/

GOOD TO KNOW (Render free plan)
   - First open after 15 min idle takes ~1 minute (it "wakes up").
   - Test donations saved there are wiped whenever Render restarts/redeploys.
     That is fine for testing; the real website keeps data permanently.
   - Emails need SMTP settings (SSY_SMTP_HOST, SSY_SMTP_USER, SSY_SMTP_PASS);
     Render does not send mail by itself.
