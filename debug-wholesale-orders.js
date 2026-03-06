/**
 * Playwright script to debug missing wholesale order details
 * Run with: node debug-wholesale-orders.js
 */

const { chromium } = require('playwright');

const CONFIG = {
  baseUrl: 'https://1-bloomzhemp-production-sync-07-24.local',
  loginUrl: 'https://1-bloomzhemp-production-sync-07-24.local/wp-login.php',
  wholesaleUrl: 'https://1-bloomzhemp-production-sync-07-24.local/wp-admin/admin.php?page=kiss-woo-customer-order-search&list_wholesale=1',
  username: 'noel@neochro.me',
  password: 'Commodore#Amiga1200',
  expectedOrderIds: [805182, 805175, 786347, 104335, 100924, 19906]
};

async function debugWholesaleOrders() {
  console.log('\n=== WHOLESALE ORDER DETAILS DEBUG ===\n');
  
  const browser = await chromium.launch({ 
    headless: false,
    slowMo: 500 // Slow down for visibility
  });
  
  const context = await browser.newContext({
    ignoreHTTPSErrors: true // For local SSL
  });
  
  const page = await context.newPage();
  
  try {
    // STEP 1: Login
    console.log('STEP 1: Logging in...');
    await page.goto(CONFIG.loginUrl);
    await page.fill('#user_login', CONFIG.username);
    await page.fill('#user_pass', CONFIG.password);
    await page.click('#wp-submit');
    await page.waitForNavigation();
    console.log('✅ Logged in successfully\n');
    
    // STEP 2: Navigate to wholesale orders page
    console.log('STEP 2: Navigating to wholesale orders page...');
    await page.goto(CONFIG.wholesaleUrl);
    await page.waitForTimeout(2000); // Wait for AJAX
    console.log('✅ Page loaded\n');
    
    // STEP 3: Check for order cards
    console.log('STEP 3: Checking for order cards...');
    const orderCards = await page.locator('.kiss-cos-order-item').all();
    console.log(`Found ${orderCards.length} order cards\n`);
    
    if (orderCards.length === 0) {
      console.log('❌ No order cards found!');
      console.log('Checking for error messages...');
      const statusText = await page.locator('.kiss-cos-status').textContent();
      console.log(`Status: ${statusText}`);
      
      // Take screenshot
      await page.screenshot({ path: 'debug-no-orders.png', fullPage: true });
      console.log('Screenshot saved: debug-no-orders.png');
      return;
    }
    
    // STEP 4: Inspect first order card details
    console.log('STEP 4: Inspecting order card details...\n');
    
    for (let i = 0; i < Math.min(orderCards.length, 3); i++) {
      const card = orderCards[i];
      
      console.log(`--- Order Card ${i + 1} ---`);
      
      // Extract all text content
      const cardHtml = await card.innerHTML();
      const cardText = await card.textContent();
      
      // Check for specific fields
      const orderId = await card.locator('.kiss-cos-order-id').textContent().catch(() => 'NOT FOUND');
      const orderStatus = await card.locator('.kiss-cos-order-status').textContent().catch(() => 'NOT FOUND');
      const orderTotal = await card.locator('.kiss-cos-order-total').textContent().catch(() => 'NOT FOUND');
      const customerName = await card.locator('.kiss-cos-customer-name').textContent().catch(() => 'NOT FOUND');
      const customerEmail = await card.locator('.kiss-cos-customer-email').textContent().catch(() => 'NOT FOUND');
      const orderDate = await card.locator('.kiss-cos-order-date').textContent().catch(() => 'NOT FOUND');
      
      console.log(`Order ID: ${orderId}`);
      console.log(`Status: ${orderStatus}`);
      console.log(`Total: ${orderTotal}`);
      console.log(`Customer: ${customerName}`);
      console.log(`Email: ${customerEmail}`);
      console.log(`Date: ${orderDate}`);
      console.log(`\nFull text: ${cardText.substring(0, 200)}...`);
      console.log('');
    }
    
    // STEP 5: Check AJAX response
    console.log('STEP 5: Checking AJAX response data...\n');
    
    // Listen for AJAX requests
    page.on('response', async (response) => {
      if (response.url().includes('kiss_woo_list_wholesale_orders')) {
        console.log('Captured AJAX response:');
        const json = await response.json();
        console.log(JSON.stringify(json, null, 2));
        
        if (json.success && json.data && json.data.orders) {
          console.log(`\nAJAX returned ${json.data.orders.length} orders`);
          
          // Check first order structure
          if (json.data.orders.length > 0) {
            const firstOrder = json.data.orders[0];
            console.log('\nFirst order structure:');
            console.log(JSON.stringify(firstOrder, null, 2));
            
            // Check for missing fields
            const requiredFields = ['id', 'total_amount', 'currency', 'billing_email', 'first_name', 'last_name'];
            const missingFields = requiredFields.filter(field => !firstOrder[field] || firstOrder[field] === '');
            
            if (missingFields.length > 0) {
              console.log(`\n❌ Missing fields: ${missingFields.join(', ')}`);
            } else {
              console.log('\n✅ All required fields present');
            }
          }
        }
      }
    });
    
    // Trigger AJAX by clicking pagination or reloading
    console.log('Reloading page to capture AJAX...');
    await page.reload();
    await page.waitForTimeout(3000);
    
    // STEP 6: Take screenshot
    console.log('\nSTEP 6: Taking screenshot...');
    await page.screenshot({ path: 'debug-wholesale-orders.png', fullPage: true });
    console.log('✅ Screenshot saved: debug-wholesale-orders.png\n');
    
  } catch (error) {
    console.error('❌ Error:', error.message);
    await page.screenshot({ path: 'debug-error.png', fullPage: true });
    console.log('Error screenshot saved: debug-error.png');
  } finally {
    await browser.close();
  }
}

// Run the debug script
debugWholesaleOrders().then(() => {
  console.log('\n=== DEBUG COMPLETE ===\n');
  process.exit(0);
}).catch(error => {
  console.error('Fatal error:', error);
  process.exit(1);
});

