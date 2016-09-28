<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CourseBotCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('eth:mattermost:coursebot')
            ->setDescription('spam the next course we have')
            ->setHelp(<<<EOT
The <info>eth:mattermost:coursebot</info> command spams the mattermost channel with the next course, 10 min prior to the course. The bot runs as cron job "* 7-18 * * 1-5"
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $diskMath = array(
            'name' => 'DiskMath',
            'prof' => 'Ueli Maurer',
        );
        $aUD = array(
            'name' => 'A&D (D&A)',
            'prof' => 'Peter Widmayer, Markus Püschel',
        );
        $linAlg = array(
            'name' => 'LinAlg',
            'prof' => 'Olga Sorkine Hornung, Özlem Imamoglu',
        );
        $eProg = array(
            'name' => 'EProg',
            'prof' => 'Thomas Gross',
        );
        $courses = array(
            '1' => array(
                '1245' => array(
                    'type' => 'day',
                ),
                '1255' => array_merge($diskMath, array(
                    'time' => '13:15-15:00',
                    'room' => 'HG E 7',
                    'video' => 'HG E 5',
                )),
            ),
            '2' => array(
                '0945' => array(
                    'type' => 'day',
                ),
                '0955' => array_merge($eProg, array(
                    'time' => '10:15-11:55',
                    'room' => 'ML D 28',
                    'video' => 'ML E 12',
                )),
            ),
            '3' => array(
                '0945' => array(
                    'type' => 'day',
                ),
                '0955' => array_merge($linAlg, array(
                    'time' => '10:15-11:55',
                    'room' => 'HG E 7',
                    'video' => 'HG E 5',
                )),
                '1255' => array_merge($diskMath, array(
                    'time' => '13:15-15:00',
                    'room' => 'HG F 1',
                    'video' => 'HG F 3',
                )),
            ),
            '4' => array(
                '0945' => array(
                    'type' => 'day',
                ),
                '0955' => array_merge($aUD, array(
                    'time' => '10:15-11:55',
                    'room' => 'ML D 28',
                    'video' => 'ML E 12',
                )),
                '1255' => array_merge($aUD, array(
                    'time' => '13:15-14:00',
                    'room' => 'ML D 28',
                    'video' => 'ML E 12',
                )),
            ),
            '5' => array(
                '0745' => array(
                    'type' => 'day',
                ),
                '0755' => array_merge($linAlg, array(
                    'time' => '08:15-10:00',
                    'room' => 'HG E 7',
                    'video' => 'HG E 5',
                )),
                '0955' => array_merge($eProg, array(
                    'time' => '10:15-11:55',
                    'room' => 'ML D 28',
                    'video' => 'ML E 12',
                )),
            ),
        );

        $day = date('N');
        if (is_array($courses) && isset($courses[$day]) && is_array($courses[$day])) {
            $time = date('Hi');
            if (isset($courses[$day][$time]) && is_array($courses[$day][$time])) {
                $course = $courses[$day][$time];

                // target webhook
                $target = null;
                $container = $this->getContainer();
                if ($container->hasParameter('mattermost')) {
                    $config = $container->getParameter('mattermost');
                    if (isset($config['webhook']) && isset($config['webhook']['coursebot'])) {
                        $target = $config['webhook']['coursebot'];
                    }
                }

                if (is_string($target) && filter_var($target, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                    if (isset($course['type']) && $course['type'] == 'day') {
                        $daily = $courses[$day];


                        // message body
                        $body = "Your courses for " . date('d.m.Y') . "\n";
                        $body .= "\n";
                        $body .= "| Course | Time | Room | Video | Prof |\n";
                        $body .= "| --- | --- | --- | --- | --- |\n";
                        foreach ($daily as $course) {
                            if (isset($course['type'])) {
                                continue;
                            }
                            $body .= "| " . (isset($course['name']) ? $course['name'] : 'Void') .
                                " | " . (isset($course['time']) ? $course['time'] : 'Void') .
                                " | " . (isset($course['room']) ? $course['room'] : 'Void') .
                                " | " . (isset($course['video']) ? $course['video'] : 'No') .
                                " | " . (isset($course['prof']) ? $course['prof'] : 'Void') . " |\n";
                        }
                        $body .= "\n";
                        $body .= "The first course will start in 30 minutes der :vorlesungsbot:";
                    } else {
                        // message body
                        $body = "| Course | Time | Room | Video | Prof |\n";
                        $body .= "| --- | --- | --- | --- | --- |\n";
                        $body .= "| " . (isset($course['name']) ? $course['name'] : 'Void') .
                            " | " . (isset($course['time']) ? $course['time'] : 'Void') .
                            " | " . (isset($course['room']) ? $course['room'] : 'Void') .
                            " | " . (isset($course['video']) ? $course['video'] : 'No') .
                            " | " . (isset($course['prof']) ? $course['prof'] : 'Void') . " |\n";
                        $body .= "\n";
                        $body .= "The course will start in 20 minutes der :vorlesungsbot:";
                    }

                    // payload construction
                    $payload = 'payload={"text": "';
                    $payload .= str_replace('"', '\"', $body);
                    $payload .= '"}';

                    // init cURL
                    $ch = curl_init();

                    // set cURL options
                    curl_setopt($ch, CURLOPT_URL, $target);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

                    // enable return and execute
                    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    /*$server_output = */
                    curl_exec($ch);

                    // close connection
                    curl_close($ch);

                    // check if the request was processed
                    /*if ($server_output == 'ok') {
                        var_dump('yayy every thing works!');
                    } else {
                        var_dump('shit! something is broken...');
                    }

                    // output return of the server
                    var_dump($server_output);
                    // output target url and sen't payload
                    var_dump($target, $payload);
                    die();*/
                }
            }
        }
    }
}