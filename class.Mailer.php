<?php

/**
 * Mailer: A very simple PHP SMTP email client.
 *
 * Copyright (c) 2017 Sei Kan
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright  2017 Sei Kan <seikan.dev@gmail.com>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 *
 * @see       https://github.com/seikan/Mailer
 */
class Mailer
{
	const TO = 1;
	const CC = 2;
	const BCC = 3;
	const SMTP = 11;
	const XOAUTH2 = 12;
	const TEXT = 21;
	const HTML = 22;
	const EOL = "\r\n";

	protected $connection;
	protected $hello = 'EHLO';
	protected $replyTo = [];
	protected $recipients = [];
	protected $attachments = [];
	protected $headers = [];
	protected $logs = [];
	protected $statusCode;
	protected $statusMessage;
	protected $readReceipt;
	protected $deliveryStatus;

	/**
	 * SMTP settings.
	 *
	 * @var array
	 */
	private $credential;

	/**
	 * Initialize Mailer object.
	 *
	 * @param string $host
	 * @param int    $port
	 * @param string $username
	 * @param string $password
	 * @param int    $method
	 */
	public function __construct($host, $port, $username, $password, $method = self::SMTP)
	{
		$this->credential = [
			'host'     => $host,
			'port'     => $port,
			'username' => $username,
			'password' => $password,
			'method'   => $method,
			'useTLS'   => false,
		];

		if (substr($host, 0, 6) == 'tls://') {
			$this->credential['useTLS'] = true;
			$this->credential['host'] = substr($host, 6);
		}
	}

	/**
	 * Terminate the connection.
	 */
	public function __destruct()
	{
		if (is_resource($this->connection)) {
			fclose($this->connection);
		}
	}

	/**
	 * Get the detailed SMTP logs.
	 *
	 * @return array
	 */
	public function getLogs()
	{
		return $this->logs;
	}

	/**
	 * Enable read receipt.
	 */
	public function enableReadReceipt()
	{
		$this->readReceipt = true;
	}

	/**
	 * Enable delivery status.
	 */
	public function enableDeliveryStatus()
	{
		$this->deliveryStatus = true;
	}

	/**
	 * Set a custom hello string.
	 *
	 * @param string $hello
	 */
	public function setHello($hello)
	{
		$this->hello = (strtoupper($hello) == 'HELO') ? 'HELO' : 'EHLO';
	}

	/**
	 * Add recipient.
	 *
	 * @param string $email
	 * @param string $name
	 * @param int    $type
	 *
	 * @return bool
	 */
	public function addAddress($email, $name = null, $type = self::TO)
	{
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->writeLog('"' . $email . '" is not a valid email address.');

			return false;
		}

		$this->recipients[] = [
			'email' => strtolower($email),
			'name'  => $name,
			'type'  => $type,
		];

		return true;
	}

	/**
	 * Set reply-to address.
	 *
	 * @param string $email
	 * @param string $name
	 *
	 * @return bool
	 */
	public function setReplyTo($email, $name = null)
	{
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$this->writeLog('"' . $email . '" is not a valid email address.');

			return false;
		}

		$this->replyTo = [
			'email' => $email,
			'name'  => $name,
		];

		return true;
	}

	/**
	 * Add attachment.
	 *
	 * @param string $file
	 * @param string $name
	 *
	 * @return bool
	 */
	public function addAttachment($file, $name = null)
	{
		if (!file_exists($file)) {
			$this->writeLog('"' . $file . '" is not accessible.');

			return false;
		}

		$fileName = basename($file);

		$this->attachments[] = [
			'name' => ($name) ? $name : $fileName,
			'type' => $this->getFileType(substr($fileName, strrpos($fileName, '.') + 1)),
			'path' => $file,
		];

		return true;
	}

	/**
	 * Add custom headers.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function addHeader($key, $value)
	{
		$this->headers[$key] = $value;
	}

	/**
	 * Send out the email.
	 *
	 * @param string $from
	 * @param string $fromName
	 * @param string $subject
	 * @param string $body
	 * @param int    $mode
	 * @param string $textBody
	 *
	 * @return bool
	 */
	public function send($from, $fromName, $subject, $body, $mode = self::TEXT, $textBody = null)
	{
		$from = strtolower($from);

		if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
			$this->writeLog('"' . $from . '" is not a valid email address.');

			return false;
		}

		if (empty($this->recipients)) {
			$this->writeLog('There is no recipient added.');

			return false;
		}

		if (!$this->connect()) {
			return false;
		}

		$this->execute('MAIL FROM: <' . $from . '>');

		if ($this->statusCode != 250) {
			$this->terminate('MAIL command failed.');

			return false;
		}

		foreach ($this->recipients as $recipient) {
			$this->execute('RCPT TO: <' . $recipient['email'] . '>' . (($this->deliveryStatus) ? ' NOTIFY=SUCCESS,FAILURE,DELAY' : ''));

			if ($this->statusCode != 250) {
				$this->terminate('RECPT TO command failed.');

				return false;
			}
		}

		$this->execute('DATA');

		if ($this->statusCode != 354) {
			$this->terminate('DATA command failed.');

			return false;
		}

		// Remove extra lines
		$subject = str_replace(["\r", "\n"], '', trim($subject));
		$body = str_replace("\r", '', trim($body));

		$contents = [];
		$boundary1 = md5(microtime() . mt_rand(1000, 9999));
		$boundary2 = md5(microtime() . mt_rand(1000, 9999));

		$headers = [
			'Message-ID: <' . time() . '.' . md5(microtime()) . '@' . substr($from, strrpos($from, '@') + 1) . '>',
			'From: ' . (($fromName) ? '"' . $this->encode($fromName) . '" ' : '') . '<' . $from . '>',
		];

		if (!empty($this->headers)) {
			foreach ($this->headers as $key => $value) {
				$headers[] = $key . ': ' . $value;
			}
		}

		if (!empty($this->replyTo)) {
			$headers[] = 'Reply-To: ' . (($this->replyTo['name']) ? '"' . $this->encode($this->replyTo['name']) . '" ' : '') . '<' . $this->replyTo['email'] . '>';
		}

		$toList = [];
		$ccList = [];
		foreach ($this->recipients as $recipient) {
			if ($recipient['type'] == self::TO) {
				$toList[] = (($recipient['name']) ? '"' . $this->encode($recipient['name']) . '" ' : '') . '<' . $recipient['email'] . '>';
			}

			if ($recipient['type'] == self::CC) {
				$ccList[] = (($recipient['name']) ? '"' . $this->encode($recipient['name']) . '" ' : '') . '<' . $recipient['email'] . '>';
			}
		}

		$headers[] = 'To: ' . implode(', ', $toList);
		$headers[] = 'CC: ' . implode(', ', $ccList);
		$headers[] = 'Subject: ' . $this->encode($subject);
		$headers[] = 'Date: ' . date('r');
		$headers[] = 'Importance: Normal';
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Return-Path: <' . $from . '>';

		if ($this->readReceipt) {
			$headers[] = 'Disposition-Notification-To: ' . (($fromName) ? '"' . $this->encode($fromName) . '" ' : '') . '<' . $from . '>';
			$headers[] = 'Return-Receipt-To: ' . (($fromName) ? '"' . $this->encode($fromName) . '" ' : '') . '<' . $from . '>';
		}

		if ($mode == self::TEXT) {
			if (empty($this->attachments)) {
				$headers[] = 'Content-Type: text/plain; charset="utf-8"';
				$headers[] = 'Content-Transfer-Encoding: 7bit';
			} else {
				$headers[] = 'Content-Type: multipart/mixed;';
				$headers[] = '	boundary="' . $boundary1 . '"';
				$headers[] = '--' . $boundary1;
				$headers[] = 'Content-Type: text/plain; charset="utf-8"';
				$headers[] = 'Content-Transfer-Encoding: 7bit';
			}
			$contents[] = $body;
		} else {
			if (!empty($this->attachments)) {
				$headers[] = 'Content-Type: multipart/mixed;';
				$headers[] = '	boundary="' . $boundary1 . '"';
			} else {
				$headers[] = 'Content-Type: multipart/alternative;';
				$headers[] = '	boundary="' . $boundary2 . '"';
			}

			if (!empty($this->attachments)) {
				$contents[] = '--' . $boundary1 . self::EOL . 'Content-Type: multipart/alternative; boundary="' . $boundary2 . '"' . self::EOL;
			}

			$contents[] = '--' . $boundary2;
			$contents[] = 'Content-Type: text/plain; charset="UTF-8"';
			$contents[] = 'Content-Transfer-Encoding: quoted-printable' . self::EOL;
			$contents[] = preg_replace('/\n\./', "\n..", quoted_printable_encode(($textBody) ? $textBody : strip_tags($body)));
			$contents[] = '--' . $boundary2;
			$contents[] = 'Content-Type: text/html; charset="UTF-8"';
			$contents[] = 'Content-Transfer-Encoding: quoted-printable' . self::EOL;
			$contents[] = preg_replace('/\n\./', "\n..", quoted_printable_encode($body));
			$contents[] = '--' . $boundary2 . '--';
		}

		if (!empty($this->attachments)) {
			foreach ($this->attachments as $attachment) {
				$contents[] = self::EOL . '--' . $boundary1;
				$contents[] = 'Content-Type: ' . $attachment['type'] . '; name="' . $attachment['name'] . '"';
				$contents[] = 'Content-Transfer-Encoding: base64';
				$contents[] = 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . self::EOL;

				$fp = fopen($attachment['path'], 'rb');
				if (!$fp) {
					$this->terminate('Error opening file "' . $attachment['path'] . '".');

					return false;
				}

				$contents[] = chunk_split(base64_encode(fread($fp, filesize($attachment['path']))));
				fclose($fp);
			}
			$contents[] = '--' . $boundary1 . '--';
		}

		$this->execute(implode(self::EOL, $headers) . self::EOL . self::EOL . implode(self::EOL, $contents) . self::EOL . '.');

		if ($this->statusCode != 250) {
			$this->terminate('DATA command failed.');

			return false;
		}

		$this->disconnect();

		return true;
	}

	/**
	 * Write log.
	 *
	 * @param mixed $s
	 */
	protected function writeLog($s)
	{
		$this->logs[] = date('Y-m-d H:i:s') . "\t" . $s;
	}

	/**
	 * Connect to remote host.
	 *
	 * @return bool
	 */
	private function connect()
	{
		$context = stream_context_create([
			'ssl' => [
				'verify_peer'      => false,
				'verify_peer_name' => false,
			],
		]);

		$this->writeLog('Connecting to "' . $this->credential['host'] . ':' . $this->credential['port'] . '".');

		$this->connection = @stream_socket_client($this->credential['host'] . ':' . $this->credential['port'], $errNo, $errStr, 30, STREAM_CLIENT_CONNECT, $context);

		if (!$this->connection) {
			$this->writeLog('Connection failed: ' . $errStr);

			return false;
		}

		$this->writeLog('Connection established.');

		// Get server banner
		$this->getResponse();

		if ($this->statusCode != 220) {
			$this->writeLog('Remote server not responding.');

			return false;
		}

		// Greet the server
		$this->execute($this->hello . ' 127.0.0.1');

		if ($this->statusCode != 250) {
			$this->terminate('Server not responding to greeting.');

			return false;
		}

		if ($this->credential['useTLS']) {
			$this->execute('STARTTLS');

			if ($this->statusCode != 220) {
				$this->terminate('STARTTLS command failed.');

				return false;
			}

			if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				$this->terminate('Unable to start TLS connection.');

				return false;
			}

			// Send greeting again
			$this->execute($this->hello . ' 127.0.0.1');

			if ($this->statusCode != 250) {
				$this->terminate('Server not responding to greeting.');

				return false;
			}
		}

		switch ($this->credential['method']) {
			case self::SMTP:
				$this->execute('AUTH LOGIN');

				if ($this->statusCode != 334) {
					$this->terminate('AUTH LOGIN not accepted.');

					return false;
				}

				$this->execute(base64_encode($this->credential['username']));

				if ($this->statusCode != 334) {
					$this->terminate('Username not accepted.');

					return false;
				}

				$this->execute(base64_encode($this->credential['password']));

				break;

			case self::XOAUTH2:
				$this->execute('AUTH XOAUTH2 ' . base64_encode('user=' . $this->credential['username'] . "\1" . 'auth=Bearer ' . $this->credential['password'] . "\1\1"));
				break;

			default:
				$this->terminate('Invalid authentication method.');

				return false;
		}

		if ($this->statusCode != 235) {
			$this->terminate('Authentication failed.');

			return false;
		}

		return true;
	}

	/**
	 * Encode text into UTF-8.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function encode($text)
	{
		if (strlen($text) != mb_strlen($text, 'utf-8')) {
			return '=?UTF-8?B?' . base64_encode($text) . '?=';
		}

		return $text;
	}

	/**
	 * Get file MIME type by extension.
	 *
	 * @param string $ext
	 *
	 * @return string
	 */
	private function getFileType($ext)
	{
		switch (strtolower($ext)) {
			case 'js':
				return 'application/x-javascript';

			case 'jpg': case 'jpeg': case 'jpe':
				return 'image/jpg';

			case 'png': case 'gif': case 'bmp': case 'tiff':
				return 'image/' . $ext;

			case 'css':
				return 'text/css';

			case 'doc': case 'docx':
				return 'application/msword';

			case 'xls': case 'xlsx': case 'xlt': case 'xlm': case 'xld': case 'xla': case 'xlc': case 'xlw': case 'xll':
				return 'application/vnd.ms-excel';

			case 'ppt': case 'pptx': case 'pps':
				return 'application/vnd.ms-powerpoint';

			case 'html': case 'htm': case 'php':
				return 'text/html';

			case 'txt':
				return 'text/plain';

			case 'mpeg': case 'mpg': case 'mpe':
				return 'video/mpeg';

			case 'mp3':
				return 'audio/mpeg3';

			case 'mp4':
				return 'video/mp4';

			case 'wav':
				return 'audio/wav';

			case 'aiff': case 'aif':
				return 'audio/aiff';

			case 'avi':
				return 'video/msvideo';

			case 'wmv':
				return 'video/x-ms-wmv';

			case 'mov':
				return 'video/quicktime';

			case 'zip': case 'gz': case 'rar': case 'rtf': case 'pdf': case 'json': case 'xml':
				return 'application/' . $ext;

			case 'tar':
				return 'application/x-tar';

			case 'swf':
				return 'application/x-shockwave-flash';

			default:
				return 'unknown/' . $ext;
		}
	}

	/**
	 * Send command to remote host.
	 *
	 * @param string $cmd
	 */
	private function execute($cmd)
	{
		@fwrite($this->connection, $cmd . self::EOL);
		$this->writeLog('# ' . $cmd);
		$this->getResponse();
	}

	/**
	 * Fetch response from remote host.
	 */
	private function getResponse()
	{
		while ($data = @fgets($this->connection, 515)) {
			$this->writeLog(trim($data));

			if (substr($data, 3, 1) == ' ') {
				break;
			}
		}
		$this->statusCode = substr($data, 0, 3);
		$this->statusMessage = substr($data, 4);
	}

	/**
	 * Disconnect from remote host.
	 */
	private function disconnect()
	{
		$this->execute('QUIT');
		fclose($this->connection);

		$this->replyTo = $this->recipients = $this->attachments = $this->headers = [];
	}

	/**
	 * Terminate connection with error message.
	 *
	 * @param string $message
	 *
	 * @return false
	 */
	private function terminate($message)
	{
		$this->disconnect();
		$this->writeLog($message);

		return false;
	}
}
