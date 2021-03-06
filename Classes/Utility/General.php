<?php

namespace Rosemary\Utility;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class General {

	/**
	 * @param $resourceName
	 * @return string
	 * @throws \Exception
	 */
	public static function getResourcePathAndName($resourceName) {
		if (is_file($_SERVER['HOME'] . '/.rosemary/Resources/' . $resourceName)) {
			return $_SERVER['HOME'] . '/.rosemary/Resources/' . $resourceName;
		} elseif (is_file(ROOT_DIR . '/Resources/' . $resourceName)) {
			return ROOT_DIR . '/Resources/' . $resourceName;
		} else {
			throw new \Exception('Cannot find resources: ' . $resourceName . '. (Looking in: ' . $_SERVER['HOME'] . '/.rosemary/Resources/, ' . ROOT_DIR . '/Resources/)');
		}
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public static function getSeeds() {
		$seedFile = $_SERVER['HOME'] . '/.rosemary/Seeds2.yaml';
		if (file_exists($seedFile) === FALSE) {
			throw new \Exception('Seed file ' . $seedFile . ' not found. ' . PHP_EOL . ' Run rosemary seed update first' . PHP_EOL);
		}

		try {
			$yaml = Yaml::parse(file_get_contents($seedFile));
			return $yaml;
		} catch (ParseException $e) {
			throw new \Exception('Error: ' . $e->getMessage());
		}
	}

	/**
	 * @param $seedName
	 * @return array|bool
	 * @throws \Exception
	 */
	public static function getSeed($seedName) {
		// Check if source is in seed files
		$siteSeeds = self::getSeeds();
		foreach ($siteSeeds as $seed => $seedConfiguration) {
			if ($seed === $seedName) {
				return $seedConfiguration;
			}
		}
		return FALSE;
	}

	public static function getConfiguration() {
		$configuration = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/../../Configuration/Rosemary.yaml'));
		if (is_file($_SERVER['HOME'] . '/.rosemary/config.yaml')) {
			$configurationHome = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($_SERVER['HOME'] . '/.rosemary/config.yaml'));
			$configuration = self::array_merge_recursive_distinct($configuration, $configurationHome);
		}
		return $configuration;
	}

	private function array_merge_recursive_distinct(array &$array1, &$array2 = null) {
		$merged = $array1;

		if (is_array($array2)) {
			foreach ($array2 as $key => $val) {
				if (is_array($array2[$key])) {
					if (array_key_exists($key, $merged) && is_array($merged[$key])) {
						$$merged[$key] = self::array_merge_recursive_distinct($merged[$key], $array2[$key]);
					} else {
						$merged[$key] = $array2[$key];
					}
				} else {
					$merged[$key] = $val;
				}
			}
		}
		return $merged;
	}

	public static function runCommand(\Symfony\Component\Console\Output\OutputInterface $output, $command, $description = NULL) {

		$log = '**********************************************************************************************************************************' . PHP_EOL;
		if ($description) {
			$log .= '** ' . $description . PHP_EOL;
		}
		$log .= '** Command: ' . $command . PHP_EOL;
		$log .= '**********************************************************************************************************************************';

		if (self::writeLog($log)) {
			$output->writeln($log, \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE);
		} else {
			$output->writeln($log);
		}

		$process = new \Symfony\Component\Process\Process($command);
		$process->setTimeout(null);
		try {
			$process->run(function ($type, $buffer) use ($output) {
				if (self::writeLog($buffer)) {
					$output->writeln($buffer, \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE);
				} else {
					$output->writeln($buffer);
				}
			});

			// executes after the command finishes
			if ($process->isSuccessful()) {
				$output->writeln('  Done' . PHP_EOL);
			} else {
				throw new \RuntimeException($process->getErrorOutput());
			}

		} catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
			$output->writeln(' !!! Command failed. Aborting');
			$output->writeln('  - Command: ' . $command);
			$output->writeln($e->getMessage());

			$log = PHP_EOL . 'Command failed. Aborting.' . PHP_EOL;
			$log .= 'Command: ' . $command . PHP_EOL;
			$log .= 'Current/working directory: ' . getcwd() . PHP_EOL;
			$log .= $process->getErrorOutput();

			self::writeLog($log);

			throw new \Exception('Command failed. Aborting: ' . $e->getMessage());
		}
	}

	public static function writeLog($content) {
		if (defined('LOG_FILE')) {
			file_put_contents(LOG_FILE, $content, FILE_APPEND);
		} else {
			return FALSE;
		}
		return TRUE;
	}

}
