<?php
require_once __DIR__ . "/config.php";

// If not authenticated, redirect to landing page
if (!isAuthenticated()) {
    header("Location: index.php");
    exit;
}

// Get user info
$userId = $_SESSION["userId"];
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name AS role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.userId = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();
generateCSRFToken();
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
                <a href="?page=folders" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="folders">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    Subjects
                </a>
                <a href="?page=websites" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="websites">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                    Projects
                </a>
                <?php if (hasPermission("manage_users")): ?>
                <a href="?page=usermanagement" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="usermanagement">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Users
                </a>
                <?php endif; ?>
                <a href="?page=requests" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="requests">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m8-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Requests
                </a>
                <a href="?page=settings" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="settings">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.607 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Settings
                </a>
                <?php if (hasPermission("view_activity_logs")): ?>
                <a href="?page=logs" class="nav-item block px-4 py-3 rounded-lg text-slate-600 hover:bg-slate-50 hover:text-navy transition relative" data-page="logs">
                    <svg class="w-5 h-5 inline-block mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Logs
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
                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Privacy notice: Nucleus uses account and project data only for academic project tracking. Public views limit personal information.
                </p>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

                const externalSearch = table.id ? scope.querySelector(`[data-table-search="#${table.id}"]`) : null;
                if (externalSearch) {
                    options.layout = {
                        topStart: 'pageLength',
                        topEnd: null,
                        bottomStart: 'info',
                        bottomEnd: 'paging'
                    };
                }

                const dataTable = new DataTable(table, options);
                table.nucleusDataTable = dataTable;
                if (externalSearch) {
                    externalSearch.addEventListener('input', function() {
                        dataTable.search(this.value).draw();
                    });
                    if (externalSearch.value) {
                        dataTable.search(externalSearch.value).draw();
                    }
                }
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

        function pageTitles(page) {
            return { dashboard: 'Dashboard', folders: 'Subjects', websites: 'Projects', 'project-form': 'Project Setup', usermanagement: 'Users', requests: 'Requests', settings: 'Settings', logs: 'Logs' }[page] || 'Nucleus';
        }

        function showFeedback(scope = document) {
            const feedback = scope.querySelector('[data-feedback]');
            if (!feedback || !window.Swal) return;

            Swal.fire({
                icon: feedback.dataset.feedback || 'info',
                title: feedback.dataset.feedbackTitle || (feedback.dataset.feedback === 'error' ? 'Something went wrong' : 'Saved'),
                text: feedback.dataset.feedbackMessage || feedback.textContent.trim(),
                confirmButtonColor: '#3085d6'
            });
        }

        let statusPollTimer = null;

        function statusBadgeClasses(status, compact = false) {
            if (compact) {
                const base = 'status-badge shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ';
                return {
                    initializing: base + 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    building: base + 'bg-amber-50 text-amber-700 ring-amber-600/20',
                    deployed: base + 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    error: base + 'bg-red-50 text-red-700 ring-red-600/20',
                }[status] || base + 'bg-red-50 text-red-700 ring-red-600/20';
            }

            return 'px-2 py-1 rounded text-sm font-medium badge-' + status;
        }

        function applyProjectStatus(badge, result) {
            const status = result.status || 'error';
            const isCardBadge = badge.classList.contains('status-badge');
            badge.className = statusBadgeClasses(status, isCardBadge);
            badge.textContent = result.displayStatus || status.charAt(0).toUpperCase() + status.slice(1);
            badge.title = result.message || '';

            const card = badge.closest('.project-card');
            if (card) {
                card.dataset.status = badge.textContent;
                if (status === 'deployed') {
                    card.dataset.updated = Math.floor(Date.now() / 1000);
                    const time = card.querySelector('.project-time');
                    if (time && result.displayUpdatedAt) time.textContent = result.displayUpdatedAt;
                }
            }
        }

        function initStatusPolling(scope = document) {
            if (statusPollTimer) {
                clearInterval(statusPollTimer);
                statusPollTimer = null;
            }

            const badges = Array.from(scope.querySelectorAll('[data-project-status-id]'));
            if (!badges.length) return;

            async function pollOnce() {
                const projectIds = Array.from(new Set(badges.map(badge => badge.dataset.projectStatusId).filter(Boolean)));
                await Promise.all(projectIds.map(async projectId => {
                    try {
                        const response = await fetch('handlers/check_project_status.php?projectId=' + encodeURIComponent(projectId), {
                            headers: { 'Accept': 'application/json' }
                        });
                        const result = await response.json();
                        if (!result.success) return;
                        scope.querySelectorAll(`[data-project-status-id="${CSS.escape(projectId)}"]`).forEach(badge => applyProjectStatus(badge, result));
                    } catch (err) {
                        console.debug('Status poll failed', err);
                    }
                }));
            }

            pollOnce();
            statusPollTimer = setInterval(pollOnce, 5000);
        }

        function renderContent(page, html) {
            contentEl.innerHTML = html;
            runInlineScripts(contentEl);
            initNucleusDataTables(contentEl);
            initStatusPolling(contentEl);
            updateActiveNav(page);
            titleEl.textContent = pageTitles(page);
            showFeedback(contentEl);
        }

        function runExternalTableSearch(input) {
            const selector = input.dataset.tableSearch;
            if (!selector) return;

            const table = contentEl.querySelector(selector);
            if (!table) return;

            const dataTable = table.nucleusDataTable;
            if (dataTable) {
                dataTable.search(input.value).draw();
                return;
            }

            const query = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(row => {
                row.classList.toggle('hidden', query !== '' && !row.textContent.toLowerCase().includes(query));
            });
        }

        function runSubjectSearch(input) {
            const cards = Array.from(contentEl.querySelectorAll('[data-subject-card]'));
            const empty = contentEl.querySelector('#subjectEmptyState');
            const query = input.value.trim().toLowerCase();
            let visible = 0;

            cards.forEach(card => {
                const matches = !query || (card.dataset.searchText || '').includes(query);
                card.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });

            if (empty) empty.classList.toggle('hidden', visible !== 0);
        }

        function loadPage(page, pushState = true, params = new URLSearchParams()) {
            const fetchParams = new URLSearchParams(params);
            fetchParams.set('tab', page);

            fetch('get_content.php?' + fetchParams.toString())
                .then(res => res.text())
                .then(html => {
                    renderContent(page, html);
                    if (pushState) {
                        const historyParams = new URLSearchParams(params);
                        historyParams.set('page', page);
                        history.pushState({ page }, '', '?' + historyParams.toString());
                    }
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
            const confirmation = await Swal.fire({
                icon: 'question',
                title: 'Mark this project as updated?',
                text: 'Version will be incremented automatically.',
                showCancelButton: true,
                confirmButtonText: 'Update',
                confirmButtonColor: '#3085d6'
            });
            if (!confirmation.isConfirmed) return;

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
                    await Swal.fire({ icon: 'success', title: 'Project marked as updated', confirmButtonColor: '#3085d6' });
                    window.location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Update failed', text: result.message, confirmButtonColor: '#3085d6' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Request failed', text: err.message, confirmButtonColor: '#3085d6' });
            }
        });

        contentEl.addEventListener('submit', async function(e) {
            const form = e.target.closest('form');
            if (!form) return;

            if (form.dataset.confirm && !form.dataset.confirmed) {
                e.preventDefault();
                const confirmation = await Swal.fire({
                    icon: 'warning',
                    title: form.dataset.confirmTitle || 'Are you sure?',
                    text: form.dataset.confirm,
                    showCancelButton: true,
                    confirmButtonText: form.dataset.confirmButton || 'Continue',
                    confirmButtonColor: '#3085d6'
                });
                if (confirmation.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                }
                return;
            }

            const action = new URL(form.action || window.location.href, window.location.href);
            if (!action.pathname.endsWith('/get_content.php')) return;

            e.preventDefault();
            const params = new URLSearchParams(action.search);
            const page = params.get('tab') || new URLSearchParams(window.location.search).get('page') || 'dashboard';

            try {
                const response = await fetch(action.toString(), {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await response.text();
                renderContent(page, html);

                const historyParams = new URLSearchParams(window.location.search);
                historyParams.set('page', page);
                params.forEach((value, key) => {
                    if (key !== 'tab') historyParams.set(key, value);
                });
                history.pushState({ page }, '', '?' + historyParams.toString());
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Request failed', text: err.message, confirmButtonColor: '#3085d6' });
            }
        });

        contentEl.addEventListener('input', function(e) {
            const input = e.target.closest('[data-table-search]');
            if (input) {
                runExternalTableSearch(input);
                return;
            }

            const subjectSearch = e.target.closest('[data-subject-search]');
            if (subjectSearch) {
                runSubjectSearch(subjectSearch);
            }
        });

        contentEl.addEventListener('click', async function(e) {
            const link = e.target.closest('a[data-confirm]');
            if (!link) return;

            e.preventDefault();
            const confirmation = await Swal.fire({
                icon: 'warning',
                title: link.dataset.confirmTitle || 'Are you sure?',
                text: link.dataset.confirm,
                showCancelButton: true,
                confirmButtonText: link.dataset.confirmButton || 'Continue',
                confirmButtonColor: '#3085d6'
            });
            if (confirmation.isConfirmed) {
                window.location.href = link.href;
            }
        });

    });
    </script>

</body>
</html>
