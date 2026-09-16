<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rbac.php';

$appUrl = rtrim((string)($appUrl ?? (defined('APP_URL') ? APP_URL : Config::get('APP_URL', 'http://localhost/factoryflow'))), '/');
$canManage = $canManage ?? has_role([ROLE_ADMIN, ROLE_MANAGER]);
?>

<?php if (!empty($canManage)): ?>
<!-- Modal: Log Stock Movement -->
<div class="modal fade" id="modalLogMovement" tabindex="-1" aria-labelledby="modalLogMovementLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded bg-primary-subtle text-primary">
                        <i class="fa-solid fa-dolly"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalLogMovementLabel">Log Stock Movement</h5>
                        <div class="text-muted small">Record inward receipt, manual dispatch, or scrap write-off.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formLogMovement" action="<?= e($appUrl) ?>/api/inventory.php" method="POST">
                <input type="hidden" name="action" value="record_movement">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
                <input type="hidden" name="movement_type" id="logMovementType" value="in">

                <div class="modal-body p-4">
                    <div class="alert alert-danger py-2 px-3 small d-none form-error-alert mb-3"></div>

                    <!-- Movement Type Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Movement Type <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group" id="moveTypeBtnGroup">
                            <button type="button" class="btn btn-outline-success active" data-move-type="in">
                                <i class="fa-solid fa-arrow-down-left me-1"></i> Stock In (Receipt)
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-move-type="out">
                                <i class="fa-solid fa-arrow-up-right me-1"></i> Stock Out (Dispatch)
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-move-type="scrap">
                                <i class="fa-solid fa-trash-can me-1"></i> Scrap / Waste
                            </button>
                        </div>
                    </div>

                    <!-- Item Type Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Item Category <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item_type" id="logTypeMaterial" value="material" checked>
                                <label class="form-check-label fw-medium" for="logTypeMaterial">
                                    Raw Material
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item_type" id="logTypeProduct" value="product">
                                <label class="form-check-label fw-medium" for="logTypeProduct">
                                    Finished Product
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Target Item Dropdown -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small fw-semibold text-secondary mb-0" for="logItemId">Select Item <span class="text-danger">*</span></label>
                            <span id="logCurrentStockBadge" class="badge bg-light text-secondary border">Select an item</span>
                        </div>
                        <select class="form-select" id="logItemId" name="item_id" required>
                            <option value="" disabled selected>Loading items...</option>
                        </select>
                    </div>

                    <!-- Quantity Input with Unit -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="logQuantity">Quantity <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.0001" min="0.0001" class="form-control fw-semibold" id="logQuantity" name="quantity" placeholder="0.00" required>
                            <span class="input-group-text text-muted" id="logUnitDisplay">units</span>
                        </div>
                    </div>

                    <!-- Batch / Reference Section -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary mb-1" for="logBatchNo">Batch / Lot #</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="logBatchNo" name="batch_no" placeholder="LOT-2026-...">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold text-secondary mb-1" for="logReferenceType">Reference Type</label>
                            <select class="form-select form-select-sm" id="logReferenceType" name="reference_type">
                                <option value="manual">Manual Entry</option>
                                <option value="purchase_order">Purchase Order (PO)</option>
                                <option value="production_order">Production Order</option>
                                <option value="customer_return">Customer Return</option>
                                <option value="scrap">Damage / Scrap</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="logReferenceNo">Reference / Order #</label>
                        <input type="text" class="form-control form-control-sm" id="logReferenceNo" name="reference_no" placeholder="e.g. PO-2026-0042 or WO-881">
                    </div>

                    <!-- Notes -->
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="logNotes">Notes / Reason</label>
                        <textarea class="form-control form-control-sm" id="logNotes" name="notes" rows="2" placeholder="Optional comments or audit trail notes..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-4 bg-light">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitLogMove">
                        <i class="fa-solid fa-check me-1"></i> Commit Movement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Stock Reconciliation / Audit Adjustment -->
<div class="modal fade" id="modalStockAdjustment" tabindex="-1" aria-labelledby="modalStockAdjustmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded bg-warning-subtle text-warning">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalStockAdjustmentLabel">Stock Reconciliation & Audit</h5>
                        <div class="text-muted small">Reconcile physical floor counts with system warehouse ledger.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formStockAdjustment" action="<?= e($appUrl) ?>/api/inventory.php" method="POST">
                <input type="hidden" name="action" value="reconcile_adjustment">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                <div class="modal-body p-4">
                    <div class="alert alert-danger py-2 px-3 small d-none form-error-alert mb-3"></div>

                    <!-- Item Type Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Item Category <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item_type" id="adjTypeMaterial" value="material" checked>
                                <label class="form-check-label fw-medium" for="adjTypeMaterial">
                                    Raw Material
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="item_type" id="adjTypeProduct" value="product">
                                <label class="form-check-label fw-medium" for="adjTypeProduct">
                                    Finished Product
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Item Selection -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="adjItemId">Select Item <span class="text-danger">*</span></label>
                        <select class="form-select" id="adjItemId" name="item_id" required>
                            <option value="" disabled selected>Loading items...</option>
                        </select>
                    </div>

                    <!-- Stock Counter Comparison Box -->
                    <div class="card bg-light border p-3 mb-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-6 border-end text-center">
                                <div class="text-muted small">Current System Stock</div>
                                <div class="fs-4 fw-bold text-dark mt-1" id="adjCurrentStockDisplay">0.00</div>
                                <div class="text-secondary small adjUnitDisplay">units</div>
                            </div>
                            <div class="col-6 text-center">
                                <label class="text-primary small fw-bold d-block" for="adjCountedStock">Physical Counted Stock</label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-lg text-center fw-bold border-primary" id="adjCountedStock" name="counted_stock" placeholder="0.00" required>
                                <div class="text-secondary small adjUnitDisplay mt-1">units</div>
                            </div>
                        </div>

                        <!-- Live Variance Indicator -->
                        <div class="mt-3 pt-2 border-top text-center">
                            <div class="small text-muted mb-1">Calculated Variance (Difference)</div>
                            <div id="adjVarianceBadge" class="badge bg-secondary-subtle text-secondary px-3 py-2 fs-6">
                                0.00 variance
                            </div>
                        </div>
                    </div>

                    <!-- Reason Selector -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="adjReason">Adjustment Reason <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="adjReason" name="reason" required>
                            <option value="Routine Cycle Count Discrepancy" selected>Routine Cycle Count Discrepancy</option>
                            <option value="Damaged Stock Write-off">Damaged Stock Write-off</option>
                            <option value="Expired / Obsolete Inventory">Expired / Obsolete Inventory</option>
                            <option value="Data Entry Correction">Correction of Prior Data Entry Error</option>
                            <option value="Annual Physical Stocktake Audit">Annual Physical Stocktake Audit</option>
                            <option value="Supplier Quantity Discrepancy">Supplier Quantity Discrepancy</option>
                            <option value="Other Discrepancy">Other Discrepancy</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-secondary mb-1" for="adjNotes">Audit Notes / Location</label>
                        <textarea class="form-control form-control-sm" id="adjNotes" name="notes" rows="2" placeholder="Auditor name, warehouse shelf location, or reconciliation justification..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top py-2 px-4 bg-light">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-semibold" id="btnSubmitStockAdj">
                        <i class="fa-solid fa-scale-balanced me-1"></i> Reconcile Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Item Movement History -->
<div class="modal fade" id="modalItemHistory" tabindex="-1" aria-labelledby="modalItemHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded bg-info-subtle text-info">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalItemHistoryLabel">Item Stock History</h5>
                        <div class="text-muted small" id="histItemSubtitle">Viewing ledger for item</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="histTable">
                        <thead class="table-light small text-uppercase text-secondary">
                            <tr>
                                <th>Date / Time</th>
                                <th>Type</th>
                                <th class="text-end">Quantity</th>
                                <th>Batch #</th>
                                <th>Reference</th>
                                <th>Notes / Reason</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody id="histTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div> Loading history...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
