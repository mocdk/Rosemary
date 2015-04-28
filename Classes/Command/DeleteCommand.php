<?php

namespace Rosemary\Command;

class DeleteCommand extends \Rosemary\Command\AbstractCommand {

	private $installationName = NULL;

	protected function configure() {
		$this
			->setName('delete')
			->setDescription('Delete an existing project')
			->addArgument('name', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Set the name of the installation');
	}

	protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output) {
		$this->input = $input;
		$this->output = $output;

		try {
			$this->validateArgumentName($input->getArgument('name'));
		} catch (Exception $e) {
			die('Error on validate arguments: ' . $e->getMessage());
		}

		$helper = $this->getHelper('question');
		$question = new \Symfony\Component\Console\Question\ConfirmationQuestion('Continue with this action? [y/N] ', false);
		if (!$helper->ask($input, $output, $question)) {
			die('Aborting..');
		}

		$this->logfile = $this->configuration['locations']['log_dir'] . '/' . 'rosemary-delete-' . date('d-m-Y-H-i-s') . '.log';
		$this->outputLine('Logging to ' . $this->logfile);

		try {
			$this->task_deleteVhost();
			$this->task_deleteDatabase();
			$this->task_deleteDirectories();
		} catch (\Exception $e) {
			die('It all stops here: ' . $e->getMessage());
		}

	}

	/*******************************************************************************************************************
	 *
	 ******************************************************************************************************************/

	/**
	 * @param $name
	 * @return bool
	 */
	protected function validateArgumentName($name) {
		$this->installationName = $name;
		return TRUE;
	}

	/*******************************************************************************************************************
	 *
	 ******************************************************************************************************************/

	private function task_deleteVhost() {
		$command = vsprintf('sudo a2dissite %s', strtolower($this->installationName));
		$this->runCommand($command, 'Unloading virtual host');

		$command = 'sudo apache2ctl graceful';
		$this->runCommand($command, 'Restart apache');

		$command = vsprintf(
			'sudo rm %s',
			array(
				$this->configuration['locations']['apache_sites'] . '/' . strtolower($this->installationName),
			)
		);
		$this->runCommand($command, 'Deleting virtual host file');
	}

	private function task_deleteDatabase() {
		$command = vsprintf(
			'mysql -h %s -u %s %s -e "DROP DATABASE IF EXISTS %s;"',
			array(
				$this->configuration['database_root']['host'],
				$this->configuration['database_root']['username'],
				($this->configuration['database_root']['password'] != '') ? '-p' . $this->configuration['database_root']['password'] : '',
				strtolower($this->installationName),
			)
		);
		$this->runCommand($command, 'Deleting database: ' . strtolower($this->installationName));
	}

	private function task_deleteDirectories() {
		$baseDir = $this->configuration['locations']['document_root'] . '/' . strtolower($this->installationName);
		$command = vsprintf(
			'rm -fR %s',
			array(
				$baseDir
			)
		);
		$this->runCommand($command, 'Deleting file structure: ' . $baseDir);
	}

}