<?php
session_start();
require __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = $_POST['student_id'] ?? null;
    $newClassId = $_POST['class_id'] ?? null;

    if ($studentId && $newClassId !== null) {
        try {
            $pdo->beginTransaction();

            // Ambil nama student & class sebelum ubah
            $stmt = $pdo->prepare("SELECT u.name AS student_name, c.class_name AS old_class FROM students s
                                    JOIN users u ON s.user_id = u.id
                                    LEFT JOIN classes c ON s.class_id = c.id
                                    WHERE s.id = ?");
            $stmt->execute([$studentId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            $studentName = $info['student_name'] ?? 'Pelajar';
            $oldClass = $info['old_class'] ?? 'Tiada';

            // Dapatkan nama kelas baru
            $stmt2 = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
            $stmt2->execute([$newClassId]);
            $newClass = $stmt2->fetchColumn() ?: 'Baru';

            // Update kelas pelajar
            $stmt3 = $pdo->prepare("UPDATE students SET class_id = ? WHERE id = ?");
            $stmt3->execute([$newClassId, $studentId]);

            $pdo->commit();
            $_SESSION['message'] = "$studentName telah dipindahkan dari kelas $oldClass ke kelas $newClass.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = "Gagal menukar kelas pelajar. Sila cuba lagi.";
        }
    }
}

header("Location: manage_classes.php?class_id=" . urlencode($_POST['class_id']));
exit;
