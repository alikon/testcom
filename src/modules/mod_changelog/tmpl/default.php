<?php
\defined('_JEXEC') or die;
?>

<div class="mod-changelog shadow-sm p-3 mb-5 bg-body rounded">
    <h3 class="mb-4 border-bottom pb-2">Project Updates</h3>
    
    <?php if (!$list || empty($list)): ?>
        <div class="alert alert-warning">Unable to load changelog from GitHub <?php echo htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($list as $item): ?>
                <div class="list-group-item py-3">
                    <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                        <h5 class="mb-1 fw-bold text-primary">
                            Version <?php echo htmlspecialchars($item->version); ?>
                        </h5>
                        <small class="badge bg-secondary opacity-75">
                            <?php echo htmlspecialchars($item->type); ?>
                        </small>
                    </div>

                    <div class="mt-2">
                        <?php if (isset($item->security) && !empty($item->security->item)): ?>
                            <div class="alert alert-danger py-2 px-3 small mb-3">
                                <strong class="d-block mb-1">⚠️ SECURITY FIXES</strong>
                                <ul class="mb-0 ps-3">
                                    <?php 
                                    // Convert single item to array if needed
                                    $securityItems = is_array($item->security->item) ? $item->security->item : [$item->security->item];
                                    foreach ($securityItems as $securityItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($securityItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->addition) && !empty($item->addition->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-success mb-1">
                                    <i class="bi bi-plus-circle"></i> ADDITIONS
                                </div>
                                <ul class="ps-3 mb-0 small">
                                    <?php 
                                    $additionItems = is_array($item->addition->item) ? $item->addition->item : [$item->addition->item];
                                    foreach ($additionItems as $additionItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($additionItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->change) && !empty($item->change->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-warning mb-1">
                                    <i class="bi bi-arrow-left-right"></i> CHANGES
                                </div>
                                <ul class="ps-3 mb-0 small">
                                    <?php 
                                    $changeItems = is_array($item->change->item) ? $item->change->item : [$item->change->item];
                                    foreach ($changeItems as $changeItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($changeItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->fix) && !empty($item->fix->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-info mb-1">
                                    <i class="bi bi-bug"></i> FIXES
                                </div>
                                <ul class="ps-3 mb-0 small">
                                    <?php 
                                    $fixItems = is_array($item->fix->item) ? $item->fix->item : [$item->fix->item];
                                    foreach ($fixItems as $fixItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($fixItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->language) && !empty($item->language->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-primary mb-1">
                                    <i class="bi bi-translate"></i> LANGUAGE UPDATES
                                </div>
                                <ul class="ps-3 mb-0 small">
                                    <?php 
                                    $languageItems = is_array($item->language->item) ? $item->language->item : [$item->language->item];
                                    foreach ($languageItems as $languageItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($languageItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->remove) && !empty($item->remove->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-danger mb-1">
                                    <i class="bi bi-trash"></i> REMOVED
                                </div>
                                <ul class="ps-3 mb-0 small">
                                    <?php 
                                    $removeItems = is_array($item->remove->item) ? $item->remove->item : [$item->remove->item];
                                    foreach ($removeItems as $removeItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($removeItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->note) && !empty($item->note->item)): ?>
                            <div class="mb-3">
                                <div class="small fw-bold text-secondary mb-1">
                                    <i class="bi bi-info-circle"></i> NOTES
                                </div>
                                <ul class="ps-3 mb-0 small text-muted">
                                    <?php 
                                    $noteItems = is_array($item->note->item) ? $item->note->item : [$item->note->item];
                                    foreach ($noteItems as $noteItem): 
                                    ?>
                                        <li><?php echo htmlspecialchars(trim($noteItem)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
