<?php
try {
    $sql = <<<SQL
ALTER TABLE `intra_users`
  ADD CONSTRAINT `FK_intra_users_intra_users_roles`
  FOREIGN KEY (`role`) REFERENCES `intra_users_roles` (`id`)
  ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `intra_audit_log`
  ADD CONSTRAINT `FK_intra_audit_log_intra_users`
  FOREIGN KEY (`user`) REFERENCES `intra_users` (`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
