<?php

/**
 * @file
 * Contains \Drupal\ipride_calendar\Command\CreateCommand.
 */

namespace Drupal\ipride_calendar\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Command\Command;
use Drupal\Console\Style\DrupalStyle;

use Drupal;
use Drupal\ipride_calendar\Util\Util;

/**
 * Class CreateCommand.
 *
 * @package Drupal\ipride_calendar
 */
class CreateCommand extends Command {
  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('ipride_calendar:create')
      ->setDescription($this->trans('command.ipride_calendar.create.description'))
      ->addOption('user_name', NULL, InputOption::VALUE_REQUIRED, $this->trans('command.ipride_calendar.create.options.user_name'))
      ->addOption('calendar_name', NULL, InputOption::VALUE_REQUIRED, $this->trans('command.ipride_calendar.create.options.calendar_name'))
      ->addOption('color', NULL, InputOption::VALUE_REQUIRED, $this->trans('command.ipride_calendar.create.options.color'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $user_name = $input->getOption('user_name');
    $calendar_name = $input->getOption('calendar_name');
    $color = $input->getOption('color');

    $client = Util::getCalendarClient($user_name);
    $uuid = Drupal::service('uuid')->generate();
    $client->createCalendar(
      $uuid,
      $calendar_name,
      $color
    );

    $output->writeln(sprintf(
      'The calendar "%s" of user "%s" is created.',
      $calendar_name,
      $user_name
    ));
  }


  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $io = new DrupalStyle($input, $output);

    // --user_name option
    $user_name = $input->getOption('user_name');
    if (!$user_name) {
      $user_name = $io->ask('User name', 'root');
      $input->setOption('user_name', $user_name);
    }

    // --calendar_name option
    $calendar_name = $input->getOption('calendar_name');
    if (!$calendar_name) {
      $calendar_name = $io->ask('Calendar name', 'default');
      $input->setOption('calendar_name', $calendar_name);
    }

    // --color option
    $color = $input->getOption('color');
    if (!$color) {
      $color = $io->ask('Color', '#000000');
      $input->setOption('color', $color);
    }
  }
}
