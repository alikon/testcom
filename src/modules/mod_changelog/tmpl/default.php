<?php
defined('_JEXEC') or die;
?>

<div class="mod-changelog shadow-sm p-3 mb-5 bg-body rounded">
    <h3 class="mb-4 border-bottom pb-2">Project Updates</h3>
    
    <?php if (!$list): ?>
        <div class="alert alert-warning">Unable to load changelog from GitHub.</div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($list as $item): ?>
                <div class="list-group-item py-3">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <h5 class="mb-1 fw-bold text-primary">
                            Version <?php echo $item->version; ?>
                        </h5>
                        <small class="badge bg-secondary opacity-75"><?php echo $item->type; ?></small>
                    </div>

                    <div class="mt-2">
                        <?php if (isset($item->addition)): ?>
                            <div class="small fw-bold text-success mb-1">ADDITIONS</div>
                            <ul class="ps-3 mb-2 small">
                                <?php foreach ($item->addition->item as $i): ?>
                                    <li><?php echo $i; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (isset($item->change)): ?>
                            <div class="small fw-bold text-warning text-dark mb-1">CHANGES</div>
                            <ul class="ps-3 mb-2 small text-muted">
                                <?php foreach ($item->change->item as $i): ?>
                                    <li><?php echo $i; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (isset($item->security)): ?>
                            <div class="alert alert-danger py-1 px-2 small mb-2">
                                <strong>Security:</strong> <?php echo $item->security->item; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($item->note)): ?>
                            <div class="text-muted fst-italic small mt-2">
                                <i class="bi bi-info-circle"></i> <?php echo $item->note->item; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>