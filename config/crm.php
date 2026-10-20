<?php

return [

    'brand' => [
        'name' => 'HayFixr',
        'tagline' => 'Maintenance & Waste',
    ],

    // Demo-only conveniences: sidebar account switcher, demo logins, "Reset demo data".
    // Always off in production regardless of the env flag (spec §14).
    'demo_switcher' => env('APP_ENV') !== 'production' && (bool) env('CRM_DEMO_SWITCHER', true),

    'gst_rate' => (float) env('CRM_GST_RATE', 0.18),
    'currency' => 'INR',
    'default_country_code' => '91',

    // Timestamps are stored in UTC (APP_TIMEZONE); this is what users see (spec §11).
    'display_timezone' => env('CRM_DISPLAY_TIMEZONE', 'Asia/Kolkata'),

    // Finance policy (spec §4.3 / §15 — confirm with product owner).
    'invoice_due_days' => 7,
    'allow_partial_payments' => (bool) env('CRM_ALLOW_PARTIAL_PAYMENTS', true),
    // Final charges that move more than this fraction from the estimate need manager/admin approval.
    'charge_approval_threshold' => (float) env('CRM_CHARGE_APPROVAL_THRESHOLD', 0.25),
    'charge_approver_roles' => ['super', 'admin', 'manager'],
    'refund_roles' => ['super', 'admin'],

    // WhatsApp policy: free-form replies only inside the customer-service window.
    'session_window_hours' => 24,
    'opt_out_keywords' => ['stop', 'unsubscribe', 'opt out', 'optout'],
    'opt_in_keywords' => ['start', 'subscribe'],

    // Booking state machine (spec §5). Anything not listed here is rejected server-side.
    'transitions' => [
        'New' => ['Confirmed', 'Cancelled'],
        'Confirmed' => ['Assigned', 'Rescheduled', 'Cancelled'],
        'Assigned' => ['Technician On The Way', 'Rescheduled', 'Cancelled'],
        'Technician On The Way' => ['Service Started', 'Rescheduled', 'Cancelled', 'No Show'],
        'Service Started' => ['Service Completed', 'Cancelled'],
        'Service Completed' => ['Invoice Generated'],
        'Invoice Generated' => ['Payment Pending', 'Paid'],
        'Payment Pending' => ['Paid', 'Cancelled'],
        'Paid' => ['Closed'],
        'Rescheduled' => ['Confirmed', 'Assigned', 'Cancelled'],
        'No Show' => ['Rescheduled', 'Cancelled'],
        'Closed' => [],
        'Cancelled' => [],
    ],
    // Exception outcomes must carry a reason (spec §4.2 step 14).
    'exception_statuses' => ['Cancelled', 'Rescheduled', 'No Show'],
    // Statuses a technician may set on their own jobs.
    'technician_statuses' => ['Technician On The Way', 'Service Started', 'Service Completed', 'No Show'],
    // Statuses only reachable through a dedicated action (charges review, payment ledger).
    'system_statuses' => ['Invoice Generated', 'Paid'],
    'active_statuses' => ['New', 'Confirmed', 'Assigned', 'Technician On The Way', 'Service Started', 'Rescheduled'],

    'statuses' => [
        'New', 'Confirmed', 'Assigned', 'Technician On The Way', 'Service Started',
        'Service Completed', 'Invoice Generated', 'Payment Pending', 'Paid', 'Closed',
    ],

    'status_colors' => [
        'New' => 'bg-slate-100 text-slate-600',
        'Confirmed' => 'bg-blue-50 text-blue-700',
        'Assigned' => 'bg-indigo-50 text-indigo-700',
        'Technician On The Way' => 'bg-violet-50 text-violet-700',
        'Service Started' => 'bg-amber-50 text-amber-700',
        'Service Completed' => 'bg-teal-50 text-teal-700',
        'Invoice Generated' => 'bg-cyan-50 text-cyan-700',
        'Payment Pending' => 'bg-orange-50 text-orange-700',
        'Paid' => 'bg-brand-50 text-brand-600',
        'Closed' => 'bg-slate-100 text-slate-500',
        'Cancelled' => 'bg-rose-50 text-rose-600',
        'Rescheduled' => 'bg-amber-50 text-amber-700',
        'No Show' => 'bg-rose-50 text-rose-600',
    ],

    'lead_stages' => ['New Lead', 'Contacted', 'Interested', 'Quotation', 'Booking Confirmed', 'Converted', 'Lost'],
    'sources' => ['WhatsApp', 'Website', 'Phone', 'Facebook', 'Instagram', 'Google', 'Referral', 'Walk-in'],
    'pay_methods' => ['Cash', 'UPI', 'Bank Transfer', 'Online Payment'],
    'time_slots' => ['09:00 AM', '11:00 AM', '01:30 PM', '03:00 PM', '05:00 PM'],
    'ticket_priorities' => ['Low', 'Medium', 'High'],
    'ticket_statuses' => ['Open', 'Assigned', 'In Progress', 'Resolved', 'Closed'],

    // WhatsApp message sent to the customer when a booking enters a status.
    'status_messages' => [
        'Confirmed' => '✅ Your HayFixr booking is confirmed. Booking ID: :ref',
        'Assigned' => '👨‍🔧 :tech will handle your service. Booking ID: :ref',
        'Technician On The Way' => '🚗 Your HayFixr technician is on the way. ETA ~25 min.',
        'Service Started' => '🔧 Your service has started. Booking ID: :ref',
        'Service Completed' => '✅ Service completed. Thank you for choosing HayFixr!',
        'Invoice Generated' => '🧾 Your invoice :invoice is ready. Amount: :amount',
        'Rescheduled' => '📅 Your booking :ref has been rescheduled to :date · :time.',
        'Cancelled' => 'Your booking :ref has been cancelled. Reply here if you need anything else.',
        'Payment Pending' => '💰 Payment reminder for :ref. Amount due: :amount',
        'Paid' => 'Payment received — thank you! ⭐ How was your experience? Rate 1–5.',
    ],

    'roles' => [
        'super' => ['label' => 'Super Admin', 'nav' => '*'],
        'admin' => ['label' => 'Admin', 'nav' => ['dashboard', 'bookings', 'leads', 'customers', 'inbox', 'technicians', 'services', 'invoices', 'payments', 'reviews', 'support', 'reports', 'automation', 'templates']],
        'manager' => ['label' => 'Branch Manager', 'nav' => ['dashboard', 'bookings', 'leads', 'customers', 'inbox', 'technicians', 'invoices', 'payments', 'reviews', 'reports']],
        'staff' => ['label' => 'Staff', 'nav' => ['dashboard', 'bookings', 'leads', 'customers', 'inbox', 'support']],
        'tech' => ['label' => 'Technician', 'nav' => ['field', 'inbox']],
    ],

    'nav' => [
        'Operations' => [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'grid'],
            'bookings' => ['label' => 'Bookings', 'icon' => 'clipboard'],
            'leads' => ['label' => 'Leads', 'icon' => 'target'],
            'customers' => ['label' => 'Customers', 'icon' => 'users'],
            'inbox' => ['label' => 'WhatsApp Inbox', 'icon' => 'chat', 'badge' => 'unread'],
            'field' => ['label' => 'My Jobs', 'icon' => 'wrench'],
        ],
        'Service' => [
            'technicians' => ['label' => 'Technicians', 'icon' => 'hardhat'],
            'services' => ['label' => 'Services', 'icon' => 'layers'],
            'branches' => ['label' => 'Branches', 'icon' => 'building'],
        ],
        'Finance' => [
            'invoices' => ['label' => 'Invoices', 'icon' => 'receipt'],
            'payments' => ['label' => 'Payments', 'icon' => 'card'],
            'reviews' => ['label' => 'Reviews', 'icon' => 'star'],
            'support' => ['label' => 'Support', 'icon' => 'life'],
        ],
        'Automation' => [
            'reports' => ['label' => 'Reports', 'icon' => 'chart'],
            'automation' => ['label' => 'Automation', 'icon' => 'bolt'],
            'templates' => ['label' => 'WA Templates', 'icon' => 'template'],
            'users' => ['label' => 'Users', 'icon' => 'shield'],
            'settings' => ['label' => 'Settings', 'icon' => 'gear'],
        ],
    ],

    'icons' => [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'clipboard' => '<rect x="8" y="3" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>',
        'chat' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18v3h3l6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2-2 2.5-2.5z"/>',
        'hardhat' => '<path d="M2 18h20"/><path d="M4 18a8 8 0 0 1 16 0"/><path d="M10 6a2 2 0 0 1 4 0v3"/><path d="M7 9V7"/><path d="M17 9V7"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>',
        'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'star' => '<polygon points="12 2 15 9 22 9.5 17 14 18.5 21 12 17.5 5.5 21 7 14 2 9.5 9 9"/>',
        'life' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m4.9 4.9 4.2 4.2M14.9 14.9l4.2 4.2M14.9 9.1l4.2-4.2M4.9 19.1l4.2-4.2"/>',
        'chart' => '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>',
        'bolt' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'template' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'shield' => '<path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3z"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6h.09A1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    ],

    // Values starting with "dummy" are placeholders: the token and app secret are
    // treated as not configured, so the app stays in local mode and never calls Meta.
    'whatsapp' => [
        'phone_id' => env('WHATSAPP_PHONE_ID', 'dummy_phone_number_id'),
        'business_id' => env('WHATSAPP_BUSINESS_ID', 'dummy_business_account_id'),
        'token' => str_starts_with(strtolower((string) env('WHATSAPP_TOKEN')), 'dummy') ? null : env('WHATSAPP_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'dummy_verify_token'),
        'app_secret' => str_starts_with(strtolower((string) env('WHATSAPP_APP_SECRET')), 'dummy') ? null : env('WHATSAPP_APP_SECRET'),
    ],

    // Password for the seeded demo accounts (development/staging only — never seeded in production).
    'demo_password' => env('CRM_DEMO_PASSWORD', 'DummyDemoPass#2026'),
];
