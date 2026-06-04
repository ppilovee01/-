<?php
$file = 'c:/xampp/htdocs/FitGear/admin_flash_sale.php';
$content = file_get_contents($file);

$pattern = '/<button onclick="deleteCampaign\(<\?= \$camp\[\'id\'\] \?>, \'<\?= get_csrf_token\(\) \?>\'\)" class="btn btn-light btn-sm rounded-3 text-danger border" title="ลบ">\s*<i class="bi bi-trash-fill"><\/i>\s*<\/button>\s*<\/div>\s*<\/td>\s*<\/tr>/';

// Let's normalize content line endings first
$content = str_replace("\r\n", "\n", $content);

$replacement = '<button onclick="deleteCampaign(<?= $camp[\'id\'] ?>, \'<?= get_csrf_token() ?>\')" class="btn btn-light btn-sm rounded-3 text-danger border" title="ลบ">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                             </tr>
                                             
                                             <!-- Mobile View -->
                                             <tr id="campaign-mob-row-<?= $camp[\'id\'] ?>" class="campaign-row d-md-none">
                                                 <td colspan="6" class="p-0 border-0">
                                                     <div class="card-modern-mobile p-3 mb-3 text-start">
                                                         <div class="d-flex align-items-center gap-3 mb-2">
                                                             <img src="<?= htmlspecialchars($camp[\'product_image\']) ?>" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                                             <div class="flex-grow-1 min-w-0">
                                                                 <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($camp[\'product_name\']) ?></div>
                                                                 <div class="d-flex align-items-center gap-2 mt-1">
                                                                     <small class="text-muted text-decoration-line-through">฿<?= number_format($camp[\'original_price\']) ?></small>
                                                                     <strong class="text-danger">฿<?= number_format($camp[\'flash_price\'], 2) ?></strong>
                                                                 </div>
                                                             </div>
                                                             <div>
                                                                 <span class="flash-badge <?= $status_class ?>"><?= $status_text ?></span>
                                                             </div>
                                                         </div>
                                                         <div class="mb-3">
                                                             <div class="small text-muted mb-1 d-flex justify-content-between" style="font-size: 0.8rem;">
                                                                 <span>ขายแล้ว <?= $camp[\'flash_sold\'] ?>/<?= $camp[\'flash_stock\'] ?> ชิ้น</span>
                                                                 <span><?= round($pct) ?>%</span>
                                                             </div>
                                                             <div class="progress" style="height: 6px;">
                                                                 <div class="progress-bar bg-blue" role="progressbar" style="width: <?= $pct ?>%"></div>
                                                             </div>
                                                         </div>
                                                         <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                                             <div style="font-size: 0.75rem;" class="text-muted">
                                                                 <div><i class="bi bi-play-circle-fill text-success me-1"></i><?= date(\'d/m/Y H:i\', $start) ?></div>
                                                                 <div><i class="bi bi-stop-circle-fill text-danger me-1"></i><?= date(\'d/m/Y H:i\', $end) ?></div>
                                                             </div>
                                                             <div class="d-flex gap-1">
                                                                 <?php if ($is_active): ?>
                                                                     <button onclick="cancelCampaign(<?= $camp[\'id\'] ?>, \'<?= get_csrf_token() ?>\')" class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1" style="font-size: 0.75rem;" title="จบแคมเปญทันที">
                                                                         <i class="bi bi-stop-fill"></i> จบแคมเปญ
                                                                     </button>
                                                                 <?php endif; ?>
                                                                 <button onclick="loadEditCampaign(<?= $camp[\'id\'] ?>)" class="btn btn-light btn-sm rounded-3 text-warning border px-2 py-1" title="แก้ไข">
                                                                     <i class="bi bi-pencil-fill"></i>
                                                                 </button>
                                                                 <button onclick="deleteCampaign(<?= $camp[\'id\'] ?>, \'<?= get_csrf_token() ?>\')" class="btn btn-light btn-sm rounded-3 text-danger border px-2 py-1" title="ลบ">
                                                                     <i class="bi bi-trash-fill"></i>
                                                                 </button>
                                                             </div>
                                                         </div>
                                                     </div>
                                                 </td>
                                             </tr>';

$new_content = preg_replace($pattern, $replacement, $content);

if ($new_content === null || $new_content === $content) {
    echo "ERROR: preg_replace failed or matched nothing!\n";
    exit(1);
}

file_put_contents($file, $new_content);
echo "SUCCESS!\n";
?>
