# Nex CEC Design System v1.0

All tokens live as CSS custom properties in `css/style.css` under `:root`.  
Use them via `var(--token-name)` anywhere in your CSS.

---

## 1. Color Tokens

### Brand Colors
| Token | Value | Usage |
|-------|-------|-------|
| `--color-primary` | `#003366` | Primary buttons, sidebar, headings |
| `--color-primary-light` | `#004080` | Hover states |
| `--color-primary-dark` | `#002244` | Active/pressed states |
| `--color-secondary` | `#ffcc00` | Gold accent, highlights |
| `--color-secondary-light` | `#ffd633` | Hover states |
| `--color-secondary-dark` | `#cca300` | Active states |
| `--color-accent` | `#e63946` | Alerts, errors, delete |

### Semantic Colors
| Token | Value | Usage |
|-------|-------|-------|
| `--color-success` | `#27ae60` | Success messages, active status |
| `--color-warning` | `#f39c12` | Warnings, pending status |
| `--color-danger` | `#e74c3c` | Errors, rejected status |
| `--color-info` | `#17a2b8` | Informational notices |

### Grays
| Token | Value | Usage |
|-------|-------|-------|
| `--color-gray-50` | `#f8f9fa` | Table headers, filter bars |
| `--color-gray-100` | `#f4f4f4` | Body background |
| `--color-gray-200` | `#e9ecef` | Borders, dividers |
| `--color-gray-300` | `#dee2e6` | Card borders |
| `--color-gray-400` | `#ced4da` | Disabled states |
| `--color-gray-500` | `#adb5bd` | Placeholder text |
| `--color-gray-600` | `#888888` | Muted text |
| `--color-gray-700` | `#555555` | Secondary text |
| `--color-gray-800` | `#333333` | Body text |
| `--color-gray-900` | `#1a1a1a` | Footer, dark elements |

### Alias Tokens (Backward Compatible)
```css
--primary-color   → var(--color-primary)
--secondary-color → var(--color-secondary)
--text-color      → var(--color-gray-800)
--light-bg        → var(--color-gray-100)
--white           → var(--color-white)
--dark-footer     → var(--color-gray-900)
```

---

## 2. Typography

### Font Families
```css
--font-family-base:   'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
--font-family-mono:   'Consolas', 'Courier New', monospace;
```

### Type Scale
| Token | Rem | PX | Usage |
|-------|-----|----|-------|
| `--text-xs` | 0.75rem | 12px | Labels, badges, meta |
| `--text-sm` | 0.80rem | ~13px | Small text, helper |
| `--text-base` | 0.875rem | 14px | **Default body text** |
| `--text-md` | 1rem | 16px | Larger body, inputs |
| `--text-lg` | 1.125rem | 18px | Card titles |
| `--text-xl` | 1.25rem | 20px | Section titles |
| `--text-2xl` | 1.5rem | 24px | Page titles |
| `--text-3xl` | 1.875rem | 30px | Hero headings |
| `--text-4xl` | 2.25rem | 36px | Large hero |
| `--text-5xl` | 3rem | 48px | Display text |

### Weights
```css
--font-normal:   400
--font-medium:   500
--font-semibold: 600
--font-bold:     700
```

### Line Heights
```css
--leading-tight:   1.2   /* Headings */
--leading-base:    1.5   /* Default */
--leading-relaxed: 1.6   /* Body */
--leading-loose:   1.8   /* Articles */
```

---

## 3. Spacing Scale

Based on a 4px grid. Use these for margins, paddings, and gaps.

| Token | Rem | PX |
|-------|-----|----|
| `--space-1` | 0.25rem | 4px |
| `--space-2` | 0.5rem | 8px |
| `--space-3` | 0.75rem | 12px |
| `--space-4` | 1rem | 16px |
| `--space-5` | 1.25rem | 20px |
| `--space-6` | 1.5rem | 24px |
| `--space-8` | 2rem | 32px |
| `--space-10` | 2.5rem | 40px |
| `--space-12` | 3rem | 48px |
| `--space-16` | 4rem | 64px |
| `--space-20` | 5rem | 80px |

---

## 4. Borders & Shadows

### Border Radius
| Token | Value | Usage |
|-------|-------|-------|
| `--radius-sm` | 4px | Small inputs |
| `--radius-md` | 6px | Buttons |
| `--radius-lg` | 8px | Cards, modals |
| `--radius-xl` | 12px | Badges, pills |
| `--radius-2xl` | 16px | Large cards |
| `--radius-full` | 50% | Avatars, circles |

### Shadows
| Token | Usage |
|-------|-------|
| `--shadow-xs` | Subtle separation |
| `--shadow-sm` | Card defaults |
| `--shadow-md` | Raised cards |
| `--shadow-lg` | Dropdowns, modals |
| `--shadow-xl` | Large modals |
| `--shadow-inner` | Inset (inputs) |

---

## 5. Component Classes

### Buttons
```html
<!-- Primary actions (admin dashboard) -->
<a class="btn-admin-action">Primary</a>
<a class="btn-admin-secondary">Secondary</a>
<a class="btn-admin-success">Success</a>
<a class="btn-admin-danger">Danger</a>
<a class="btn-admin-sm">Small</a>

<!-- Public site -->
<a class="btn-cta">Call to Action</a>
<a class="btn-login">Login</a>
<button class="btn-submit">Submit</button>

<!-- Back navigation -->
<a class="btn-back">← Back</a>

<!-- Size modifiers -->
<button class="btn-sm">Small</button>
```

### Badges / Tags
```html
<span class="badge badge-success">Active</span>
<span class="badge badge-warning">Pending</span>
<span class="badge badge-danger">Rejected</span>
<span class="badge badge-info">Info</span>
<span class="badge badge-primary">New</span>
<span class="badge badge-secondary">Draft</span>

<!-- Sizes -->
<span class="badge badge-sm">Tiny</span>
<span class="badge badge-lg">Large</span>

<!-- Dot indicator -->
<span class="badge-dot" style="background: var(--color-success)"></span>
```

### Avatars
```html
<div class="avatar avatar-primary">JD</div>
<div class="avatar avatar-sm avatar-success">A</div>
<div class="avatar avatar-lg avatar-warning">B</div>
<div class="avatar avatar-xl"><img src="photo.jpg" alt=""></div>

<!-- Stacked group -->
<div class="avatar-group">
  <div class="avatar avatar-sm avatar-primary">A</div>
  <div class="avatar avatar-sm avatar-success">B</div>
  <div class="avatar avatar-sm avatar-warning">C</div>
</div>
```

### Cards
```html
<div class="card">
  <img src="..." alt="">
  <div class="card-content">
    <h3 class="card-title">Title</h3>
    <p>Content...</p>
  </div>
</div>

<!-- Grid wrapper -->
<div class="card-grid">
  <div class="card">...</div>
  <div class="card">...</div>
</div>
```

### Tables
```html
<div class="table-responsive">
  <table class="table">
    <thead><tr><th>Name</th><th>Status</th></tr></thead>
    <tbody><tr><td>John</td><td><span class="badge badge-success">Active</span></td></tr></tbody>
  </table>
</div>
```

### Forms
```html
<div class="form-group">
  <label class="required">Email</label>
  <input type="email" class="form-control" placeholder="Enter email">
  <small class="form-help">We'll never share your email.</small>
</div>

<!-- Validation -->
<div class="form-group">
  <label>Password</label>
  <input type="password" class="form-control is-invalid">
  <div class="invalid-feedback">Password is required.</div>
</div>

<!-- Grid layout -->
<div class="grid-form">
  <div class="form-group">...</div>
  <div class="form-group">...</div>
  <div class="form-group full-width">...</div>
</div>
```

### Modals
```html
<div class="modal" id="myModal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2>Modal Title</h2>
    <p>Content...</p>
  </div>
</div>
```

### Alerts
```html
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Error message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-info">Info message</div>
```

### Tabs
```html
<!-- Underline tabs -->
<div class="tabs">
  <button class="tab-item active">Tab 1</button>
  <button class="tab-item">Tab 2</button>
</div>

<!-- Pill tabs -->
<div class="tabs tabs-pills">
  <a href="#" class="tab-item active">Active</a>
  <a href="#" class="tab-item">Inactive</a>
</div>
<div class="tab-content active">Panel 1</div>
<div class="tab-content">Panel 2</div>
```

### Progress Bars
```html
<div class="progress-label">
  <span>Completion</span>
  <span class="value">75%</span>
</div>
<div class="progress">
  <div class="progress-bar" style="width: 75%"></div>
</div>

<!-- Variants -->
<div class="progress-bar progress-bar-success" style="width: 60%"></div>
<div class="progress-bar progress-bar-warning" style="width: 40%"></div>
<div class="progress-bar progress-bar-danger" style="width: 20%"></div>
```

### Breadcrumbs
```html
<nav class="breadcrumb">
  <a href="dashboard.php">Dashboard</a>
  <span class="separator">/</span>
  <a href="students.php">Students</a>
  <span class="separator">/</span>
  <span class="current">Edit Student</span>
</nav>
```

### Pagination
```html
<div class="pagination">
  <a href="#">&laquo;</a>
  <a href="#">1</a>
  <span class="active">2</span>
  <a href="#">3</a>
  <a href="#">&raquo;</a>
</div>
```

### Empty State
```html
<div class="empty-state">
  <i class="fas fa-inbox"></i>
  <h3>No messages yet</h3>
  <p>When you receive messages, they will appear here.</p>
</div>
```

### List Group
```html
<div class="list-group">
  <div class="list-group-item">
    <i class="fas fa-file"></i>
    <div>
      <div class="list-group-title">Item Title</div>
      <div class="list-group-text">Description text</div>
    </div>
  </div>
</div>
```

---

## 6. Layout Patterns

### Admin Dashboard Layout
```php
<div class="dashboard-container">
    <?php echo renderSidebar('page_name', $school_name); ?>
    <main class="main-content" id="main-content">
        <div class="top-bar">
            <h2>Page Title</h2>
            <div class="user-info">Welcome, User</div>
        </div>
        <!-- Page content here -->
    </main>
</div>
```

### Parent Portal Layout
```html
<aside class="parent-sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="logo.png" alt="Logo">
        <h3>School Name</h3>
        <p>Parent Portal</p>
    </div>
    <ul>
        <li><a href="dashboard.php"><i class="fas fa-home"></i> My Children</a></li>
        <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>
<div class="parent-main">
    <!-- Content -->
</div>
```

### Stat Cards Grid
```html
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-details">
            <h3>150</h3>
            <p>Total Students</p>
        </div>
    </div>
    <!-- Repeat for each stat -->
</div>
```

---

## 7. Utility Classes

| Category | Classes |
|----------|---------|
| **Text align** | `.text-left`, `.text-center`, `.text-right` |
| **Flex** | `.flex`, `.flex-wrap`, `.flex-col`, `.items-center`, `.items-start`, `.justify-between`, `.justify-center` |
| **Gap** | `.gap-5`, `.gap-8`, `.gap-10`, `.gap-15`, `.gap-20` |
| **Width** | `.w-full`, `.max-w-300`, `.max-w-500` |
| **Margin** | `.mt-{5,10,15,20}`, `.mb-{5,10,15,20,30}` |
| **Padding** | `.p-{10,15,20}` |
| **Font weight** | `.fw-bold`, `.fw-500`, `.fw-600` |
| **Font size** | `.fs-small`, `.fs-smaller`, `.fs-large` |
| **Colors** | `.color-primary`, `.color-secondary`, `.color-success`, `.color-danger`, `.color-warning`, `.color-muted` |
| **Border radius** | `.rounded`, `.rounded-full` |
| **Other** | `.position-relative`, `.overflow-hidden`, `.inline-block`, `.object-cover`, `.no-print` |

---

## 8. Responsive Breakpoints

```css
/* Mobile-first: base styles are mobile */
/* Tablet / small desktop */
@media (max-width: 768px) { ... }

/* Small phones */
@media (max-width: 480px) { ... }

/* Print */
@media print { ... }
```

---

## 9. Animations

Available as CSS classes via keyframes:

| Keyframe | Effect |
|----------|--------|
| `fadeIn` | Opacity 0 → 1 |
| `fadeInUp` | Slide up + fade |
| `fadeInDown` | Slide down + fade |
| `fadeInLeft` | Slide left + fade |
| `fadeInRight` | Slide right + fade |
| `scaleIn` | Scale 0.9 → 1 + fade |
| `slideUp` | Slide + scale |
| `pulse` | Scale pulse |
| `shimmer` | Loading skeleton |
| `spin` | Rotation |
| `bounceIn` | Bounce entrance |
| `ripple` | Button ripple |

Use animation delay via stagger tokens:
```css
animation: fadeInUp 0.4s var(--ease-out-expo) both;
animation-delay: var(--stagger-2);
```
