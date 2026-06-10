-- Add assigned_staff_id column to manifests table
ALTER TABLE manifests
    ADD COLUMN assigned_staff_id INT NULL DEFAULT NULL AFTER customer_id;

ALTER TABLE manifests
    ADD CONSTRAINT fk_manifests_assigned_staff
    FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL;
