<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact Us</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<?php
function send_message($name, $email, $message) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=edupredict", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $email, $message]);

    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = htmlspecialchars(trim($_POST["name"]));
    $email = htmlspecialchars(trim($_POST["email"]));
    $message = htmlspecialchars(trim($_POST["message"]));

    if (send_message($name, $email, $message)) {
        echo "<script>alert('Message sent successfully!'); window.location.href='contact.html';</script>";
    } else {
        echo "<script>alert('Failed to send message. Try again.'); window.location.href='contact.html';</script>";
    }
}
?>

  <header class="nav-container">
    <div class="logo-group">
      <img src="picture/ktesaa.jpg" alt="School Logo" class="logo-img school-logo" />
      <img src="picture/logo.jpg" alt="EduPredict Logo" class="logo-img edupredict-logo" />
    </div>
    <nav>
      <ul class="nav-list">
        <li><a href="index.html">HOME</a></li>
        <li><a href="faq.html">FAQ</a></li>
        <li><a href="about.html">ABOUT</a></li>
        <li><a href="contact.php" class="active">CONTACT</a></li>
        <li><a href="login.php" class="btn btn-login" style="color: white;">LOGIN</a></li>
      </ul>
    </nav>
  </header>

  <section class="contact-section">
    <div class="contact-container">
      <h1 class="contact-title">Contact Us</h1>
      <form action="contact.php" method="POST" class="contact-form">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="Your full name" required />
        </div>
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="name@example.com" required />
        </div>
        <div class="form-group full">
          <label>Message *</label>
          <textarea name="message" rows="5" placeholder="Write your message here..." required></textarea>
        </div>
        <button type="submit" class="btn btn-submit">SEND</button>
      </form>
    </div>
  </section>

  <footer>
    <p>&copy; 2024 EduPredict. All rights reserved.</p>
  </footer>
</body>
</html>
