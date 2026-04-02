<div class="card" data-dua-id="<?php echo $item['id']; ?>">
    <div class="card-header">
        <h3><?php echo htmlspecialchars($item['dua_name']); ?></h3>
        <p dir="rtl" style="font-size: 18px; color: #666;"><?php echo htmlspecialchars($item['dua_name_arabic']); ?></p>
    </div>
    <div class="progress-container">
        <div class="progress-label">
            <span class="progress-label-text">Total: <?php echo $item['completed_count']; ?> / <?php echo $item['target_count']; ?></span>
            <span class="progress-label-value"><?php echo $item['progress_percentage']; ?>%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo min($item['progress_percentage'], 100); ?>%"></div>
        </div>
    </div>
    <form class="tracking-form" style="padding: 20px;">
        <input type="hidden" name="dua_id" value="<?php echo $item['id']; ?>">
        <input type="hidden" name="entry_date" value="<?php echo date('Y-m-d'); ?>">
        
        <div class="form-group">
            <label for="count_to_add_<?php echo $item['id']; ?>">
                <i class="fas fa-plus"></i> Add Count *
            </label>
            <input type="number" 
                   id="count_to_add_<?php echo $item['id']; ?>" 
                   name="count_to_add" 
                   class="form-control" 
                   min="1" 
                   placeholder="Enter count to add"
                   required>
            <small class="count-helper" style="color: #666;">This will be added to your current total of <?php echo $item['completed_count']; ?></small>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-plus"></i> Add Entry
        </button>
        <?php if ($item['last_updated']): ?>
            <p class="text-center mt-2" style="font-size: 12px; color: #666;">
                Last entry: <?php echo date('M d, Y', strtotime($item['last_updated'])); ?>
            </p>
        <?php endif; ?>
    </form>

    <!-- View History Link -->
    <div style="padding: 0 20px 20px 20px; text-align: center;">
        <a href="dua_history.php?dua_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-history"></i> View Entry History
        </a>
    </div>
</div>