<?php
/**
 * Kalika Enterprises - UI/UX Component Design System Styleguide
 * Phase 3 Deliverable - Demonstrates all responsive design tokens and components.
 */

declare(strict_types=1);

$pageTitle = 'Design System & Styleguide';
$currentPage = 'styleguide';
$pageDescription = 'Component library and design system styleguide for Kalika Enterprises wholesale distribution web platform.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-bottom: 4rem;">
    <!-- Styleguide Header & Viewport Indicator -->
    <div
        style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 2px solid var(--border-color);">
        <div>
            <span class="badge badge-warning" style="margin-bottom: 0.5rem;">Phase 3 Deliverable</span>
            <h1>Design System &amp; UI Components</h1>
            <p style="margin-bottom: 0;">Mobile-first responsive tokens, interactive controls, and wholesale
                distribution UI modules.</p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <div class="breakpoint-indicator">
                <span id="screenDimension">Resolving...</span>
            </div>
            <a href="<?= public_url('products.php') ?>" class="btn btn-outline btn-sm">Storefront Preview &rarr;</a>
        </div>
    </div>

    <!-- 1. Color Palette Tokens -->
    <section style="margin-bottom: 3.5rem;">
        <h2>1. Harmonious Color Palette Tokens</h2>
        <p>Tailored HSL-calibrated palette suited for a professional FMCG food and beverage distributor. Neutral base
            with warm golden amber accent (derived from brand logo placeholder).</p>

        <div class="grid grid-cols-4" style="gap: 1rem;">
            <!-- Primary Navy -->
            <div class="card" style="padding: 1rem;">
                <div
                    style="height: 60px; background-color: #0f172a; border-radius: var(--radius-sm); margin-bottom: 0.75rem;">
                </div>
                <strong style="font-size: 0.875rem;">Primary Slate</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">#0f172a /
                    --color-primary</div>
            </div>

            <!-- Accent Amber -->
            <div class="card" style="padding: 1rem;">
                <div
                    style="height: 60px; background-color: #d97706; border-radius: var(--radius-sm); margin-bottom: 0.75rem;">
                </div>
                <strong style="font-size: 0.875rem;">Warm Amber (Accent)</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">#d97706 /
                    --color-accent</div>
            </div>

            <!-- Success Emerald -->
            <div class="card" style="padding: 1rem;">
                <div
                    style="height: 60px; background-color: #059669; border-radius: var(--radius-sm); margin-bottom: 0.75rem;">
                </div>
                <strong style="font-size: 0.875rem;">In-Stock Emerald</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">#059669 /
                    --color-success</div>
            </div>

            <!-- Danger Crimson -->
            <div class="card" style="padding: 1rem;">
                <div
                    style="height: 60px; background-color: #dc2626; border-radius: var(--radius-sm); margin-bottom: 0.75rem;">
                </div>
                <strong style="font-size: 0.875rem;">Out-of-Stock Crimson</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">#dc2626 /
                    --color-danger</div>
            </div>
        </div>
    </section>

    <!-- 2. Typography Scale -->
    <section class="card" style="margin-bottom: 3.5rem; padding: 2rem;">
        <h2>2. Typography Hierarchy</h2>
        <p>Google Fonts pairing: <strong>Outfit</strong> for authoritative commercial headings, <strong>Inter</strong>
            for high-legibility body data and pricing.</p>

        <div style="display: flex; flex-direction: column; gap: 1.25rem; margin-top: 1.5rem;">
            <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
                <h1>Heading 1 – 32px / 2.0rem Bold (Outfit)</h1>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Used for main page titles and hero
                    banners</span>
            </div>
            <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
                <h2>Heading 2 – 26px / 1.625rem Bold (Outfit)</h2>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Used for section titles and modal
                    headers</span>
            </div>
            <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
                <h3>Heading 3 – 20px / 1.25rem Bold (Outfit)</h3>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Used for product names and card
                    titles</span>
            </div>
            <div style="border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
                <p style="margin-bottom: 0; font-size: 1rem;">Body Text – 16px / 1.0rem Regular (Inter). Clear contrast
                    for commercial catalog browsing, invoice item descriptions, and delivery guidelines.</p>
            </div>
            <div>
                <small style="color: var(--text-muted); font-size: 0.8125rem;">Footnote / Microcopy – 13px / 0.8125rem.
                    Used for GST notes, status timestamps, and legal caveats.</small>
            </div>
        </div>
    </section>

    <!-- 3. Interactive Buttons & Accessible Tap Targets -->
    <section style="margin-bottom: 3.5rem;">
        <h2>3. Buttons &amp; Touch Target Compliance (&ge;44px)</h2>
        <p>All interactive buttons feature accessible minimum touch dimensions, active feedback states, and visible
            keyboard focus rings.</p>

        <div class="card" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div>
                <h4 style="margin-bottom: 0.75rem;">Button Styles &amp; Variants</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
                    <button type="button" class="btn btn-primary">Primary Button</button>
                    <button type="button" class="btn btn-secondary">Secondary Navy</button>
                    <button type="button" class="btn btn-outline">Outline Neutral</button>
                    <button type="button" class="btn btn-primary btn-sm">Small Button</button>
                    <button type="button" class="btn btn-primary" disabled>Disabled State</button>
                </div>
            </div>

            <div>
                <h4 style="margin-bottom: 0.75rem;">Quantity Selector Stepper</h4>
                <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                    <div class="qty-control">
                        <button type="button" class="qty-btn" data-qty="minus"
                            aria-label="Decrease quantity">&minus;</button>
                        <input type="text" class="qty-input" value="1" readonly aria-label="Selected quantity">
                        <button type="button" class="qty-btn" data-qty="plus" aria-label="Increase quantity">+</button>
                    </div>
                    <span style="font-size: 0.875rem; color: var(--text-muted);">Interactive touch stepper with min 1
                        and max 999 bounds</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Badges & Status Indicators -->
    <section style="margin-bottom: 3.5rem;">
        <h2>4. Semantic Badges &amp; Status Indicators</h2>
        <p>Status badges explicitly specified for FMCG stock levels, GST tax approvals, and order delivery lifecycles.
        </p>

        <div class="card">
            <div class="grid grid-cols-3" style="gap: 1.5rem;">
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Stock Availability</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-start;">
                        <span class="badge badge-stock-in">&#9679; In Stock</span>
                        <span class="badge badge-stock-out">&#9679; Out of Stock</span>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom: 0.5rem;">Catalogue Compliance</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-start;">
                        <span class="badge badge-gst-pending">&#9888; GST Pending (Sundar Glucose)</span>
                        <span class="badge" style="background: #e2e8f0; color: #475569;">Unconfirmed (Coca-Cola)</span>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom: 0.5rem;">Order Lifecycle States</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-start;">
                        <span class="badge badge-status-placed">1. Placed</span>
                        <span class="badge badge-status-dispatched">2. Dispatched</span>
                        <span class="badge badge-status-delivered">3. Delivered</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Form Elements & Validation -->
    <section style="margin-bottom: 3.5rem;">
        <h2>5. Form Inputs &amp; Controls</h2>
        <p>Accessible form fields featuring visible focus rings, labels on every input, and input addons.</p>

        <div class="card">
            <div class="grid grid-cols-2" style="gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label" for="sg_text">Retail Store Name *</label>
                    <input type="text" id="sg_text" class="form-control"
                        placeholder="e.g. Mahalakshmi Provision Stores">
                </div>

                <div class="form-group">
                    <label class="form-label" for="sg_mobile">10-Digit Mobile Number *</label>
                    <div class="input-group">
                        <span class="input-addon">+91</span>
                        <input type="tel" id="sg_mobile" class="form-control" placeholder="9876543210"
                            pattern="[0-9]{10}">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sg_select">Service Area Region *</label>
                    <select id="sg_select" class="form-control">
                        <option value="">Choose District / Tehsil</option>
                        <option value="niphad">Niphad (Pimpalgaon Baswant)</option>
                        <option value="dindori">Dindori</option>
                        <option value="chandwad">Chandwad</option>
                        <option value="sinnar">Sinnar</option>
                        <option value="yeola">Yeola</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sg_currency">Wholesale Amount Addon</label>
                    <div class="input-group">
                        <span class="input-addon">₹</span>
                        <input type="text" id="sg_currency" class="form-control" value="2,000.00" readonly>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Alerts & Flash Banners -->
    <section style="margin-bottom: 3.5rem;">
        <h2>6. Alerts &amp; Minimum Order Notification</h2>
        <p>Color-coded feedback banners with auto-dismiss timers and clear action prompts.</p>

        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <!-- Minimum Order Requirement Banner -->
            <div class="alert alert-warning" role="alert">
                <span><strong>Minimum Order Threshold:</strong> Cart total is ₹1,650.00. Add ₹350.00 more to unlock free
                    delivery and checkout.</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()"
                    aria-label="Close">&times;</button>
            </div>

            <!-- Success Alert -->
            <div class="alert alert-success" role="alert">
                <span><strong>Order Confirmed:</strong> Your wholesale order KE-20261011-001 has been logged and sent to
                    our dispatch desk.</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()"
                    aria-label="Close">&times;</button>
            </div>

            <!-- Error Alert -->
            <div class="alert alert-error" role="alert">
                <span><strong>Validation Error:</strong> The entered mobile number is already registered to another
                    retailer profile.</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()"
                    aria-label="Close">&times;</button>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info" role="alert">
                <span><strong>Demo OTP Notice:</strong> Verification code generated in demo mode (no external SMS
                    gateway required).</span>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()"
                    aria-label="Close">&times;</button>
            </div>
        </div>
    </section>

    <!-- 7. Product Cards Component -->
    <section style="margin-bottom: 3.5rem;">
        <h2>7. Product Card Component</h2>
        <p>Standardized product cards featuring placeholder fallback, brand pills, dynamic variant pickers, and stock
            status.</p>

        <div class="grid grid-cols-3" style="gap: 1.5rem;">
            <!-- Active Card with Variants -->
            <div class="card" style="display: flex; flex-direction: column;">
                <div
                    style="height: 180px; background: var(--bg-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; border: 1px dashed var(--border-color); color: var(--text-muted); font-size: 0.875rem;">
                    Photo coming soon
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span class="badge" style="background:#fee2e2; color:#dc2626;">Campa</span>
                    <span class="badge badge-stock-in">&#9679; In Stock</span>
                </div>
                <h3>Campa Orange</h3>
                <p style="font-size: 0.8125rem; margin-bottom: 0.75rem;">Sparkling orange fruit flavored drink. GST: 28%
                </p>

                <div class="form-group" style="margin-bottom: 0.75rem; margin-top: auto;">
                    <label class="form-label" style="font-size: 0.8125rem;">Select Bottle Size:</label>
                    <select class="form-control" style="min-height: 38px; font-size: 0.875rem;">
                        <option>200ml – ₹10.00</option>
                        <option selected>500ml – ₹20.00</option>
                        <option>700ml – ₹35.00</option>
                        <option>1L – ₹80.00</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                    <span style="font-size: 1.3rem; font-weight: 800; color: var(--color-primary);">₹20.00</span>
                    <button type="button" class="btn btn-primary btn-sm">Add to Cart</button>
                </div>
            </div>

            <!-- Out of Stock Product -->
            <div class="card" style="display: flex; flex-direction: column; opacity: 0.85;">
                <div
                    style="height: 180px; background: var(--bg-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; border: 1px dashed var(--border-color); color: var(--text-muted); font-size: 0.875rem;">
                    Photo coming soon
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span class="badge" style="background:#d1fae5; color:#059669;">Euro Chips</span>
                    <span class="badge badge-stock-out">&#9679; Out of Stock</span>
                </div>
                <h3>Euro Banana Chips</h3>
                <p style="font-size: 0.8125rem; margin-bottom: 0.75rem;">Crispy salted banana wafers. Restocking soon.
                </p>

                <div class="form-group" style="margin-bottom: 0.75rem; margin-top: auto;">
                    <label class="form-label" style="font-size: 0.8125rem;">Pack Size:</label>
                    <select class="form-control" style="min-height: 38px; font-size: 0.875rem;" disabled>
                        <option>160g – ₹50.00</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
                    <span style="font-size: 1.3rem; font-weight: 800; color: var(--text-muted);">₹50.00</span>
                    <button type="button" class="btn btn-primary btn-sm" disabled>Out of Stock</button>
                </div>
            </div>

            <!-- Inactive Product / GST Pending -->
            <div class="card"
                style="display: flex; flex-direction: column; background: #fffdf5; border-color: #fde68a;">
                <div
                    style="height: 180px; background: #fef9c3; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; border: 1px dashed #f59e0b; color: #b45309; font-size: 0.875rem;">
                    Unpublished Catalogue Item
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span class="badge" style="background:#fef3c7; color:#b45309;">Sundar</span>
                    <span class="badge badge-gst-pending">&#9888; GST Pending</span>
                </div>
                <h3>Sundar Glucose</h3>
                <p style="font-size: 0.8125rem; margin-bottom: 0.75rem;">Seeded inactive. Prohibited from public display
                    until GST rate is entered in Admin.</p>

                <div style="margin-top: auto; padding-top: 1rem;">
                    <button type="button" class="btn btn-outline btn-sm btn-block" disabled>Not Available
                        Publicly</button>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. Responsive Horizontal Scrolling Table (360px mobile tested) -->
    <section style="margin-bottom: 3.5rem;">
        <h2>8. Responsive Data Table (Horizontal Scroll on Mobile)</h2>
        <p>Demonstrates scrollable data container preventing mobile viewport overflow at 360px and 768px.</p>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Retailer Store</th>
                        <th>Service Area</th>
                        <th>Subtotal</th>
                        <th>GST (Included)</th>
                        <th>Delivery</th>
                        <th>Grand Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>KE-20261011-001</strong></td>
                        <td>Shree Ganesh Provision</td>
                        <td>Pimpalgaon Baswant</td>
                        <td>₹2,500.00</td>
                        <td>₹437.50</td>
                        <td><span style="color:var(--color-success); font-weight:bold;">FREE</span></td>
                        <td><strong>₹2,500.00</strong></td>
                        <td><span class="badge badge-status-placed">Placed</span></td>
                    </tr>
                    <tr>
                        <td><strong>KE-20261010-089</strong></td>
                        <td>Sai Krupa Traders</td>
                        <td>Dindori</td>
                        <td>₹4,200.00</td>
                        <td>₹588.00</td>
                        <td><span style="color:var(--color-success); font-weight:bold;">FREE</span></td>
                        <td><strong>₹4,200.00</strong></td>
                        <td><span class="badge badge-status-dispatched">Dispatched</span></td>
                    </tr>
                    <tr>
                        <td><strong>KE-20261009-045</strong></td>
                        <td>Omkar General Store</td>
                        <td>Chandwad</td>
                        <td>₹3,150.00</td>
                        <td>₹441.00</td>
                        <td><span style="color:var(--color-success); font-weight:bold;">FREE</span></td>
                        <td><strong>₹3,150.00</strong></td>
                        <td><span class="badge badge-status-delivered">Delivered</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 9. Interactive Modal Component -->
    <section style="margin-bottom: 3.5rem;">
        <h2>9. Interactive Modal Dialog Component</h2>
        <p>Vanilla JavaScript dialog with backdrop blur, focus trap, and keyboard Escape listener.</p>

        <div class="card" style="text-align: center; padding: 2.5rem;">
            <h3>Test Modal Dialog</h3>
            <p style="margin-bottom: 1.5rem;">Click the button below to test the animated accessible modal component.
            </p>
            <button type="button" class="btn btn-primary" data-modal-open="demoModal">
                Open Demo Modal Dialog
            </button>
        </div>
    </section>

    <!-- 10. Order Fulfillment Tracker -->
    <section style="margin-bottom: 3.5rem;">
        <h2>10. Order Fulfillment Progress Tracker</h2>
        <p>Visual status progression for customer order tracking (Placed &rarr; Dispatched &rarr; Delivered).</p>

        <div class="card" style="padding: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
                <div style="text-align: center; flex: 1;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-success); color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 800;">
                        &#10003;</div>
                    <div style="font-weight: 700; margin-top: 0.5rem;">1. Placed</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Within 24 Hours</div>
                </div>
                <div style="height: 4px; background: var(--color-success); flex: 1; margin: 0 10px;"></div>
                <div style="text-align: center; flex: 1;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-accent); color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 800;">
                        2</div>
                    <div style="font-weight: 700; margin-top: 0.5rem; color: var(--color-accent);">2. Dispatched</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">En route in delivery van</div>
                </div>
                <div style="height: 4px; background: var(--border-color); flex: 1; margin: 0 10px;"></div>
                <div style="text-align: center; flex: 1;">
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: var(--border-color); color: var(--text-muted); display: inline-flex; align-items: center; justify-content: center; font-weight: 800;">
                        3</div>
                    <div style="font-weight: 600; margin-top: 0.5rem; color: var(--text-muted);">3. Delivered</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">2-3 working days</div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Demo Modal Dialog Markup -->
<div class="modal-backdrop" id="demoModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Quick Product View</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                <div
                    style="width: 80px; height: 80px; background: var(--bg-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: var(--text-muted); border: 1px dashed var(--border-color);">
                    Photo
                </div>
                <div>
                    <span class="badge" style="background:#fee2e2; color:#dc2626;">Campa</span>
                    <h4 style="margin: 0.25rem 0;">Campa Cola (500ml)</h4>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--color-primary);">₹20.00</span>
                </div>
            </div>
            <p style="font-size: 0.875rem; color: var(--text-muted);">
                Refreshing beverage packed in cases of 24 units. Free delivery applicable on orders of ₹2,000 or more.
            </p>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Order Quantity (Units):</label>
                <div class="qty-control">
                    <button type="button" class="qty-btn" data-qty="minus">&minus;</button>
                    <input type="text" class="qty-input" value="24" readonly>
                    <button type="button" class="qty-btn" data-qty="plus">+</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Add to Wholesale Cart</button>
        </div>
    </div>
</div>

<script>
    // Breakpoint viewport detector
    function updateDimension() {
        const w = window.innerWidth;
        const badge = document.getElementById('screenDimension');
        if (!badge) return;
        let label = 'Desktop';
        if (w <= 480) label = 'Mobile (' + w + 'px)';
        else if (w <= 899) label = 'Tablet (' + w + 'px)';
        else label = 'Desktop (' + w + 'px)';
        badge.textContent = label;
    }
    window.addEventListener('resize', updateDimension);
    document.addEventListener('DOMContentLoaded', updateDimension);
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
