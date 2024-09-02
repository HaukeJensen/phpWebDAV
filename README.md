# PHP WebDAV Client

This is a simple PHP WebDAV client that can be used to interact with WebDAV servers.

Example usage:

```php
$client = new WebDAV\Client();

$url = 'https://YOURURL.com/public.php/webdav';
$username = '';
$password = '';

try {
	// Connect to the WebDAV server
	$client->connect($url, $username, $password);

	$dir = 'test/';

	// List all files in the root directory
	$files = $client->getFolderItemCollection('/');

	// Create the directory if it does not exist
	if(!in_array($dir, $files)){
		$client->createDirectory($dir);
	}

	// Upload a file to the directory
	$server_file = '/test/test.txt';
	$client->upload('Test!!', $server_file);

	// Download the file to test2.txt
	file_put_contents(__DIR__.'/test2.txt', $client->download($server_file));

	// Delete the file
	$client->deleteFile($server_file);

	// Upload a file to the directory
	$client->uploadFile(__DIR__.'/test2.txt', $server_file);

	// Download the file to test3.txt
	$client->downloadFile($server_file, __DIR__.'/test3.txt');
} catch(Exception $e) {
	echo $e->getMessage();
}
```