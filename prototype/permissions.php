<?php
/**
 * Permission System Utility Functions
 * Include this file in pages that need permission checks
 */

/**
 * Check if user can edit a record
 * Only the creator or admin can request edits
 * @param PDO $pdo Database connection
 * @param int $userId Current user ID
 * @param int $recordId Record to check
 * @param string $userRole Current user role
 * @return bool True if can edit
 */
function canEditRecord($pdo, $userId, $recordId, $userRole = null) {
    if (!$userId || !$recordId) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT created_by FROM records WHERE id = :id");
        $stmt->execute([':id' => $recordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) return false;
        
        // Creator can always request edits
        if ($record['created_by'] == $userId) return true;
        
        // Admin can request edits for any record
        if ($userRole === 'admin') return true;
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get record created by user (for ownership check)
 * @param PDO $pdo Database connection
 * @param int $recordId Record ID
 * @return array|null Record data with creator info
 */
function getRecordWithCreator($pdo, $recordId) {
    try {
        $stmt = $pdo->prepare(
            "SELECT r.*, u.name as creator_name 
             FROM records r 
             LEFT JOIN users u ON r.created_by = u.id 
             WHERE r.id = :id"
        );
        $stmt->execute([':id' => $recordId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get status badge for record
 * @param bool $isApproved Is record approved
 * @param string $approverName Name of approving admin
 * @return string HTML badge
 */
function getRecordStatusBadge($isApproved, $approverName = null) {
    if ($isApproved) {
        $badge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>';
        if ($approverName) {
            $badge .= ' <small class="text-muted">by ' . htmlspecialchars($approverName) . '</small>';
        }
    } else {
        $badge = '<span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Pending Approval</span>';
    }
    return $badge;
}

/**
 * Get pending edit requests for a record
 * @param PDO $pdo Database connection
 * @param int $recordId Record ID
 * @return array Pending edit requests
 */
function getPendingEditsForRecord($pdo, $recordId) {
    try {
        $stmt = $pdo->prepare(
            "SELECT er.*, u.name as requested_by_name
             FROM edit_requests er
             JOIN users u ON er.requested_by = u.id
             WHERE er.record_id = :record_id AND er.status = 'pending'
             ORDER BY er.requested_at DESC"
        );
        $stmt->execute([':record_id' => $recordId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Create an edit request
 * @param PDO $pdo Database connection
 * @param int $recordId Record to edit
 * @param int $userId User requesting edit
 * @param array $changes Array of changes (field => new_value)
 * @param string $reason Reason for edit
 * @return bool Success
 */
function createEditRequest($pdo, $recordId, $userId, $changes, $reason = '') {
    try {
        // Get current record data
        $recStmt = $pdo->prepare("SELECT * FROM records WHERE id = :id");
        $recStmt->execute([':id' => $recordId]);
        $record = $recStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) return false;
        
        // Extract previous values for changed fields
        $previousData = [];
        foreach ($changes as $field => $newValue) {
            if (isset($record[$field])) {
                $previousData[$field] = $record[$field];
            }
        }
        
        // Create the edit request
        $stmt = $pdo->prepare(
            "INSERT INTO edit_requests 
                (record_id, requested_by, previous_data, requested_changes, reason, status, requested_at)
             VALUES
                (:record_id, :requested_by, :previous_data, :requested_changes, :reason, 'pending', NOW())"
        );
        
        return $stmt->execute([
            ':record_id' => $recordId,
            ':requested_by' => $userId,
            ':previous_data' => json_encode($previousData),
            ':requested_changes' => json_encode($changes),
            ':reason' => $reason
        ]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Approve an edit request and apply changes
 * @param PDO $pdo Database connection
 * @param int $editId Edit request ID
 * @param int $adminId Admin approving
 * @return bool Success
 */
function approveEditRequest($pdo, $editId, $adminId) {
    try {
        // Get edit request
        $editStmt = $pdo->prepare("SELECT * FROM edit_requests WHERE id = :id AND status = 'pending'");
        $editStmt->execute([':id' => $editId]);
        $edit = $editStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit) return false;
        
        // Mark as approved
        $appStmt = $pdo->prepare(
            "UPDATE edit_requests SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id"
        );
        $appStmt->execute([':admin_id' => $adminId, ':id' => $editId]);
        
        // Apply changes to record
        $changes = json_decode($edit['requested_changes'], true);
        $setClauses = [];
        $params = [':record_id' => $edit['record_id']];
        
        foreach ($changes as $field => $value) {
            $setClauses[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        if (!empty($setClauses)) {
            $updateSql = "UPDATE records SET " . implode(', ', $setClauses) . " WHERE id = :record_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Reject an edit request
 * @param PDO $pdo Database connection
 * @param int $editId Edit request ID
 * @param int $adminId Admin rejecting
 * @return bool Success
 */
function rejectEditRequest($pdo, $editId, $adminId) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE edit_requests SET status = 'rejected', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :id AND status = 'pending'"
        );
        return $stmt->execute([':admin_id' => $adminId, ':id' => $editId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Prevent direct record modification (call this in update handlers)
 * @param PDO $pdo Database connection
 * @param int $recordId Record being modified
 * @param int $userId User attempting modification
 * @param string $userRole User role
 * @return bool Whether modification is allowed
 */
function isDirectEditAllowed($pdo, $recordId, $userId, $userRole) {
    // Only admins can directly edit records, and only for specific actions like approval
    // Secretaries must use the edit request system
    if ($userRole !== 'admin') {
        return false; // Secretaries must use edit requests
    }
    
    // Even admins might be restricted in some contexts
    // Return true for admin, false for others
    return true;
}
