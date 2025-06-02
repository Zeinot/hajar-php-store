-- Order Status History Table for tracking order status changes
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger to automatically add a history record when an order is created or its status changes
DELIMITER //
CREATE TRIGGER IF NOT EXISTS order_status_update_trigger
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO order_status_history (order_id, status, notes, created_by)
        VALUES (NEW.id, NEW.status, CONCAT('Order status changed from ', OLD.status, ' to ', NEW.status), NEW.updated_by);
    END IF;
END //

CREATE TRIGGER IF NOT EXISTS order_status_insert_trigger
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT INTO order_status_history (order_id, status, notes, created_by)
    VALUES (NEW.id, NEW.status, 'Order created', NEW.user_id);
END //
DELIMITER ;
