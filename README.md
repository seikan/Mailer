# Mailer

This is a very simple PHP SMTP email client.



## Usage

### Configuration

> \$mailer = new Mailer( **string** $host, **int** \$port, **string** \$username, **string** \$password\[, **int** \$method = Mailer::SMTP\] );

**Method**

`Mailer::SMTP` - Normal SMTP authentication.

`Mailer::XOAUTH2` - Uses oAuth SMTP server.

```php
$mailer = new Mailer('ssl://smtp.example.com', 465, 'me@example.com', 'my@password', Mailer::SMTP);
```



### Enable Read Receipt

Requests a read receipt when recipient email client supports this feature.

> \$mailer->enableReadReceipt( );

```php
$mail->enableReadReceipt();
```



### Enable Delivery Status Report

Enables delivery status report. Only supported by some SMTP servers.

> \$mailer->enableDeliveryStatus( );

```php
$mailer->enableDeliveryStatus();
```



### Custom Hello

Some SMTP servers required custom hello string. 

>  \$mailer->setHello( **string** \$hello );

```php
$mailer->setHello('EHLO');
```



### Add Recipient

Adds recipient for the email.

> **bool** \$mailer->addAddress( **string** \$email\[, **string** \$name\]\[, **int** \$type = Mailer::TO\] );

| Type        | Description                              |
| ----------- | ---------------------------------------- |
| Mailer::TO  | Normal recipient.                        |
| Mailer::CC  | Carbon copy of the email to this recipient. |
| Mailer::BCC | Blind carbon copy of the email. Others recipient will no seeing this recipient in the list. |

```php
// Add a recipient
$mailer->addAddress('peter@example.com', 'Peter');

// CC the email to alice@exmaple.com
$mailer->addAddress('alice@example.com', 'Alice', Mailer::CC);

// BCC the email to boss
$mailer->addAddress('boss@example.com', 'Boss', Mailer::BCC);
```



### Reply-To

Sets a reply-to address if you want recipient to reply your email to another address.

> **bool** \$mailer->setReplyTo(**string** \$email\[, **string** \$name\]);

```php
$mailer->setReplyTo('another@example.com', 'Another Me');
```



### Attachment

Adds attachment.

> **bool** \$mailer->addAttachment( **string** \$file\[, **string** $name\] );

```php
$mailer->addAttachment('/home/me/revenue_report.docx');
```



### Custom Headers

Adds custom headers.

> \$mailer->addHeader( **string** \$key, **string** \$value );

```php
$mailer->addHeader('X-Mailer', 'My-Custom-Email-Client');
```



### Send Email

Sends the email.

> **bool** \$mailer->send( **string** \$from_email, **string** \$from_name, **string** $subject, **string** \$body\[, **int** \$mode = Mailer::TEXT\]\[, **string** \$plain_text_body\] );

**Mode**

`Mailer::TEXT` - Sends a plain text email.

`Mailer::HTML` - Sends a HTML email.

```php
// Send plain text email
$result = $mailer->send('me@example.com', 'Me', 'Test Message', 'This is just a test message.');

// Send HTML email
$result = $mailer->send('me@example.com', 'Me', 'Test Message', 'This is just a <strong>test message</strong>.', Mailer::HTML, 'This is just a test message.');
```



### SMTP Logs

Gets SMTP logs.

> **array** \$mailer->getLogs();

```php
// Print out full SMTP logs
echo '<pre>';
echo htmlspecialchars(implode("\n", $mailer->getLogs()));
echo '</pre>';
```

