<?php

namespace OroCRM\Bundle\MailChimpBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Oro\Bundle\ImportExportBundle\Job\JobExecutor;
use Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry;
use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use OroCRM\Bundle\MailChimpBundle\Entity\StaticSegment;
use OroCRM\Bundle\MailChimpBundle\Entity\Repository\StaticSegmentRepository;
use OroCRM\Bundle\MailChimpBundle\Model\StaticSegment\StaticSegmentAwareInterface;

class SynchronizeSegmentsCommand extends ContainerAwareCommand implements CronCommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultDefinition()
    {
        return '*/5 * * * *';
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('oro:cron:mailchimp:sync-segment')
            ->setDescription('Synchronize static segments with MailChimp')
            ->addOption(
                'segments',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'MailChimp static Segments to sync'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $segments = $input->getOption('segments');
        /** @var StaticSegment[] $iterator */
        $iterator = $this->getStaticSegmentRepository()->getStaticSegmentsWithDynamicMarketingList($segments);
        $jobExecutor = $this->getJobExecutor();

        $jobs = [
            'mailchimp_marketing_list_subscribe' => 'Subscribe Members',
            'mailchimp_static_segment_member_add_state' => 'Add Segment Members',
            'mailchimp_static_segment_member_remove_state' => 'Remove Segment Members',
        ];

        foreach ($iterator as $segment) {
            $jobOptions = [ProcessorRegistry::TYPE_IMPORT => [StaticSegmentAwareInterface::OPTION_SEGMENT => $segment]];

            $output->writeln(sprintf('<info>Segment #%s: </info>', $segment->getId()));
            foreach ($jobs as $job => $message) {
                $jobResult = $jobExecutor->executeJob(ProcessorRegistry::TYPE_IMPORT, $job, $jobOptions);
                $output->writeln(
                    sprintf('    <info>%s: %s</info>', $message, $jobResult->isSuccessful() ? 'Success' : 'Failed')
                );
            }
        }
    }

    /**
     * @return StaticSegmentRepository
     */
    protected function getStaticSegmentRepository()
    {
        return $this->getContainer()->get('doctrine')->getRepository(
            $this->getContainer()->getParameter('orocrm_mailchimp.entity.static_segment.class')
        );
    }

    /**
     * @return JobExecutor
     */
    protected function getJobExecutor()
    {
        return $this->getContainer()->get('oro_importexport.job_executor');
    }
}
