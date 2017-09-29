<?php

// Configuration
$config = [
	'smtpHost' => 'ssl://smtp.example.com',
	'smtpPort' => 465,
	'smtpUser' => 'me@example.com',
	'smtpPass' => 'y4CGmd3ADMFz',
];

// Include core Mailer library
require_once 'class.Mailer.php';

// Initialize Mailer object with SMTP support
$mailer = new Mailer($config['smtpHost'], $config['smtpPort'], $config['smtpUser'], $config['smtpPass'], Mailer::SMTP);

// Initialize Mailer object with XOAUTH2 support, use this mode for SMTP server with oAuth enabled
// $mailer = new Mailer($config['smtpHost'], $config['smtpPort'], $config['smtpUser'], $config['smtpPass'], Mailer::XOAUTH2);

// Add a recipient
$mailer->addAddress('peter@example.com', 'Peter');

// CC the email to alice@exmaple.com
$mailer->addAddress('alice@example.com', 'Alice', Mailer::CC);

// BCC the email to boss
$mailer->addAddress('boss@example.com', 'Boss', Mailer::BCC);

// Attach a README.md to the email
$mailer->addAttachment('README.md');

// Send as text email
$result = $mailer->send('me@example.com', 'Me', 'Test Message', 'This is just a test message.');

// Send as HTML email
// $result = $mailer->send('me@example.com', 'Me', 'Test Message', 'This is just a <strong>test message</strong>.', Mailer::HTML, 'This is just a test message.');

if ($result) {
	echo 'Email sent.';
} else {
	echo 'Something wrong!';
}

// Print out full SMTP logs
echo '<pre>';
echo htmlspecialchars(implode("\n", $mailer->getLogs()));
echo '</pre>';
