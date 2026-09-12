<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

$appUrl = rtrim((string)Config::get('APP_URL', 'http://localhost/factoryflow'), '/');
$flash = get_flash();
?>
        </main>

        <footer class="ff-footer">
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
                <div>
                    <strong><?= e(APP_NAME) ?></strong> v<?= e(APP_VERSION) ?> &copy; <?= date('Y') ?> &bull; Enterprise Manufacturing Operations
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-success"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.55rem;"></i> System Operational</span>
                </div>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= e($appUrl) ?>/assets/js/app.js"></script>

<?php if ($flash): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        App.toast('<?= e($flash['type']) ?>', '<?= e($flash['message']) ?>');
    });
</script>
<?php endif; ?>
</body>
</html>
