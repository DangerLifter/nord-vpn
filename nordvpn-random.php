<?php

class NordVpn
{
	private $_exe = 'C:\Program Files (x86)\NordVPN\nordvpn.exe';

	public function getServers(): array
	{
		$content = file_get_contents('https://api.nordvpn.com/server');
		$servers = json_decode($content, true);
		if (!is_array($servers)) {
			throw new \RuntimeException('Failed retrieve list of servers');
		}
		return $servers;
	}

	public function connectToServer(array $server): self
	{
		$cmd = sprintf('"%s" -c -i %s', $this->_exe, $server['id']);
		exec($cmd);
		$this->waitMyIpChanged($server['ip_address'], 3, 40);
		return $this;
	}

	public function disconnect(): self
	{
		$cmd = sprintf('"%s" -d', $this->_exe);
		exec($cmd);
		return $this;
	}

	public function isMyIpMatch(string $expectedIp): bool
	{
		$content = @file_get_contents('https://www.myip.com/');
		if (!$content) return false;
		// ignore last digits because actual and expected vpn may differ in last digit.
		$searchIp = substr($expectedIp, 0, strrpos($expectedIp, '.', 0));
		return false !== stripos($content, $searchIp);
	}

	private function waitMyIpChanged(string $expectedIp, int $delay, int $timeout): self
	{
		$end = time() + $timeout;
		do {
			$success = true;
			if (!$this->isConnected()) {
				echo "Not connected\n";
				$success = false;
			};
			if ($success && !$this->isMyIpMatch($expectedIp)) {
				echo "Wrong IP\n";
				$success = false;
			};
			if ($success) return $this;
			sleep($delay);
		} while ($end > time());
		throw new \RuntimeException('Ip change failed');
	}

	private function isConnected(): bool
	{
		$connected = @fsockopen("www.example.com", 80);
		if ($connected){
			fclose($connected);
			return true;
		}

		return false;
	}
}

function run (array $argv): void
{
	if (!isset($argv[1])) {
		$argv[1] = '-c';
	}

	$nordVpn = new NordVpn();

	if ($argv[1] === '-d') {
		$nordVpn->disconnect();
		return;
	}

	$servers = $nordVpn->getServers();
	$randIndex = random_int(0, count($servers) - 1);
	$randomServer = $servers[$randIndex];
	echo $randomServer['id'].': '.$randomServer['ip_address']."\n";
	$nordVpn->disconnect();
	$nordVpn->connectToServer($randomServer);
	echo "VPN Connected\n";
}

try {
	run($argv);
} catch (\Exception $e) {
	echo $e->getMessage();
	exit(1);
}

exit(0);