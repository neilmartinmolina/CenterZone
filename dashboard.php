<?php
require_once __DIR__ . "/config.php";

// If not authenticated, redirect to landing page
if (!isAuthenticated()) {
    header("Location: index.php");
    exit;
}

// Get user info
$userId = $_SESSION["userId"];
$stmt = $pdo->prepare("SELECT * FROM users WHERE userId = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nucleus | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.tailwindcss.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#043873',
                        accent: '#FFE492',
                        cta: '#4F9CF9'
                    }
                }
            }
        }
    </script>
    <style>
        .nav-item.active {
            background-color: rgba(4, 56, 115, 0.1);
            color: #043873;
            font-weight: 600;
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #043873;
            border-radius: 0 4px 4px 0;
        }
        .dt-container .dt-search input,
        .dt-container .dt-length select {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            color: #334155;
            font-size: 0.875rem;
            outline: none;
        }
        .dt-container .dt-search input:focus,
        .dt-container .dt-length select:focus {
            border-color: #4F9CF9;
            box-shadow: 0 0 0 2px rgba(79, 156, 249, 0.2);
        }
        .dt-container .dt-paging .dt-paging-button.current {
            background: #043873 !important;
            border-color: #043873 !important;
            color: #ffffff !important;
        }
        .dt-container .dt-paging .dt-paging-button:hover {
            background: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #043873 !important;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-slate-200 flex flex-col">
            <!-- Logo -->
            <div class="h-16 flex items-center px-6 border-b border-slate-200">
                <div class="text-xl font-bold tracking-tight text-navy">NUCLEUS</div>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 py-6 px-3 space-y-1">
                <a href="?page=dashboard" class="nav-item active block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="dashboard">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                    Dashboard
                </a>
                <a href="?page=websites" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="websites">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    Websites
                </a>
                <a href="?page=folders" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="folders">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    Folders
                </a>
                <?php if (hasPermission("manage_users")): ?>
                <a href="?page=usermanagement" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="usermanagement">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Users
                </a>
                <?php endif; ?>
            </nav>

            <!-- User mini profile -->
            <div class="p-4 border-t border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-navy text-white flex items-center justify-center text-sm font-semibold">
                        <?php echo strtoupper(substr($currentUser['fullName'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate"><?php echo htmlspecialchars($currentUser['fullName'] ?? 'User'); ?></p>
                        <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($currentUser['role'] ?? 'user'); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Top Navbar -->
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
                <h1 class="text-xl font-bold text-navy" id="pageTitle">Dashboard</h1>
                <div class="flex items-center gap-4">
                    <a href="logout.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-navy transition">Logout</a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-auto p-8" id="pageContent">
                <!-- Content loaded via AJAX -->
            </main>

        </div>
    </div>

    <script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.3.8/js/dataTables.tailwindcss.min.js"></script>

    <!-- JavaScript for AJAX navigation -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const contentEl = document.getElementById('pageContent');
        const titleEl = document.getElementById('pageTitle');
        const navLinks = document.querySelectorAll('.nav-item');

        function initNucleusDataTables(scope = document) {
            if (!window.DataTable) return;

            scope.querySelectorAll('table.data-table').forEach(table => {
                if (DataTable.isDataTable(table)) return;

                const options = {
                    autoWidth: false,
                    pageLength: Number(table.dataset.pageLength || 10),
                    lengthMenu: [5, 10, 25, 50],
                    order: table.dataset.orderColumn
                        ? [[Number(table.dataset.orderColumn), table.dataset.orderDirection || 'asc']]
                        : [],
                    scrollX: true,
                    language: {
                        search: '',
                        searchPlaceholder: 'Search records...',
                        lengthMenu: 'Show _MENU_',
                        emptyTable: table.dataset.empty || 'No records found',
                        zeroRecords: 'No matching records found'
                    }
                };

                if (table.dataset.scrollY) {
                    options.scrollY = table.dataset.scrollY;
                    options.scrollCollapse = true;
                }

                if (table.querySelector('th.no-sort')) {
                    options.columnDefs = [{ targets: 'no-sort', orderable: false, searchable: false }];
                }

                new DataTable(table, options);
            });
        }

        function runInlineScripts(scope = document) {
            scope.querySelectorAll('script').forEach(script => {
                const replacement = document.createElement('script');
                Array.from(script.attributes).forEach(attr => replacement.setAttribute(attr.name, attr.value));
                replacement.textContent = script.textContent;
                script.replaceWith(replacement);
            });
        }

        function loadPage(page, pushState = true, params = new URLSearchParams()) {
            const fetchParams = new URLSearchParams(params);
            fetchParams.set('tab', page);

            fetch('get_content.php?' + fetchParams.toString())
                .then(res => res.text())
                .then(html => {
                    contentEl.innerHTML = html;
                    runInlineScripts(contentEl);
                    initNucleusDataTables(contentEl);
                    if (pushState) {
                        const historyParams = new URLSearchParams(params);
                        historyParams.set('page', page);
                        history.pushState({ page }, '', '?' + historyParams.toString());
                    }
                    updateActiveNav(page);
                    // Update page title
                    const titles = { dashboard: 'Dashboard', websites: 'Websites', folders: 'Folders', 'project-form': 'Project Setup', usermanagement: 'User Management' };
                    titleEl.textContent = titles[page] || 'Nucleus';
                })
                .catch(err => {
                    contentEl.innerHTML = '<div class="p-8 text-red-600">Failed to load page.</div>';
                    console.error(err);
                });
        }

        function updateActiveNav(page) {
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.dataset.page === page) link.classList.add('active');
            });
        }

        // Initial load
        const urlParams = new URLSearchParams(window.location.search);
        const initialPage = urlParams.get('page') || 'dashboard';
        loadPage(initialPage, false, urlParams);

        // Nav clicks
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                loadPage(this.dataset.page);
            });
        });

        // Back/forward
        window.addEventListener('popstate', function(e) {
            const params = new URLSearchParams(window.location.search);
            const page = params.get('page') || 'dashboard';
            loadPage(page, false, params);
        });

        // Global handler for status-select buttons (dashboard "Mark updated")
        contentEl.addEventListener('click', async function(e) {
            const btn = e.target.closest('.status-select');
            if (!btn) return;
            const websiteId = btn.dataset.websiteId;
            if (!confirm('Mark this project as updated? Version will be incremented automatically.')) return;

            try {
                const response = await fetch('handlers/update_website.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: '<?php echo $_SESSION["csrf_token"]; ?>',
                        websiteId: websiteId,
                        status: 'updated'
                    })
                });
                const result = await response.json();
                if (result.success) {
                    alert('Project marked as updated!');
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        });

    });
    </script>

</body>
</html>
