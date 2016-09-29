<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MensaBotCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('eth:mattermost:mensabot')
            ->setDescription('spam the mensa menu')
            ->setHelp(<<<EOT
The <info>eth:mattermost:mensabot</info> command spams the mattermost channel with the mensa menu. The bot runs as cron job "40 11 * * 1-5"
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // target webhook
        $target = null;
        $container = $this->getContainer();
        if ($container->hasParameter('mattermost')) {
            $config = $container->getParameter('mattermost');
            if (isset($config['webhook']) && isset($config['webhook']['mensabot'])) {
                $target = $config['webhook']['mensabot'];
            }
        }

        if (is_string($target) && filter_var($target, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            $json = file_get_contents('https://www.webservices.ethz.ch/gastro/v1/RVRI/Q1E1/meals/de/' . date('Y-m-d') . '/lunch?mensas=12');
            $response = json_decode($json, true);

            if (is_array($response) && count($response)) {
                $mensa = array_shift($response);
                if (isset($mensa['meals']) && count($mensa['meals'])) {
                    $body = "Meals for " . $mensa['mensa'] . ":\n";
                    $body .= "\n";
                    $body .= "| Name | Type | Description | Price |\n";
                    $body .= "| --- | --- | --- | --- |\n";
                    foreach ($mensa['meals'] as $meal) {
                        $body .= "| " . (isset($meal['label']) ? $meal['label'] : 'Void') .
                            " | " . (isset($meal['type']) ? $meal['type'] : 'Void') .
                            " | " . (isset($meal['description']) && count($meal['description']) ? implode(', ', $meal['description']) : 'Void') .
                            " | " . (isset($meal['prices']) && isset($meal['prices']['student']) ? $meal['prices']['student'] . ' CHF' : (isset($meal['prices']) && count($meal['prices']) ? array_shift($meal['prices']) . ' CHF' : 'Void')) . " |\n";
                    }
                    $body .= "\n";
                    $body .= "[Menü Clausiusbar (Asia)](https://www.ethz.ch/de/campus/gastronomie/menueplaene/offerDay.html?language=de%26id=4%26date=" . date('Y-m-d') . ")\n";
                    $body .= "[Menü foodLab (Teigwaren)](https://www.ethz.ch/de/campus/gastronomie/menueplaene/offerDay.html?language=de%26id=8%26date=" . date('Y-m-d') . ")\n";
                    $body .= "[Alle Mensas](https://www.ethz.ch/de/campus/gastronomie/menueplaene.html)\n";
                    $body .= "En Guetä wünscht der :vorlesungsbot:";
                }
            }
            // alle: https://www.ethz.ch/de/campus/gastronomie/menueplaene.html

            // payload construction
            $payload = 'payload={"text": "';
            $payload .= str_replace("&", '%26', str_replace('"', '\"', $body));
            $payload .= '"}';

            // init cURL
            $ch = curl_init();

            // set cURL options
            curl_setopt($ch, CURLOPT_URL, $target);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // enable return and execute
            /*curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = */curl_exec($ch);

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
            var_dump($target);
            //print_r($payload);
            die();*/
        }
    }
}