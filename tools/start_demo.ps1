$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCommand) {
	throw 'PHP CLI was not found on PATH.'
}
$php = $phpCommand.Source
$demoConfig = Join-Path $projectRoot 'application\config\demo\config.php'
if (-not (Test-Path -LiteralPath $php)) {
	throw 'PHP CLI was not found on PATH.'
}
if (-not (Test-Path -LiteralPath $demoConfig)) {
	throw 'The local demo configuration was not found.'
}

$hadCiEnv = Test-Path Env:\CI_ENV
$previousCiEnv = $env:CI_ENV
$env:CI_ENV = 'demo'
$exitCode = 1

try {
	& $php -d ("auto_prepend_file=" + $demoConfig) -S '127.0.0.1:18800' -t $projectRoot
	$exitCode = $LASTEXITCODE
}
finally {
	if ($hadCiEnv) {
		$env:CI_ENV = $previousCiEnv
	}
	else {
		Remove-Item Env:\CI_ENV -ErrorAction SilentlyContinue
	}
}

exit $exitCode
