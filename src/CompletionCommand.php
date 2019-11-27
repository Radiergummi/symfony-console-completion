<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\HookFactories\BashHookFactory;
use Stecman\Component\Symfony\Console\BashCompletion\HookFactories\FishHookFactory;
use Stecman\Component\Symfony\Console\BashCompletion\HookFactories\ZshHookFactory;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompletionCommand extends SymfonyCommand
{
    /**
     * @var CompletionHandler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    public function getNativeDefinition()
    {
        return $this->createDefinition();
    }

    /**
     * Ignore user-defined global options
     *
     * Any global options defined by user-code are meaningless to this command.
     * Options outside of the core defaults are ignored to avoid name and shortcut conflicts.
     *
     * @param bool $mergeArgs
     */
    public function mergeApplicationDefinition($mergeArgs = true)
    {
        // Get current application options
        $appDefinition = $this->getApplication()->getDefinition();
        $originalOptions = $appDefinition->getOptions();

        // Temporarily replace application options with a filtered list
        $appDefinition->setOptions(
            $this->filterApplicationOptions($originalOptions)
        );

        parent::mergeApplicationDefinition($mergeArgs);

        // Restore original application options
        $appDefinition->setOptions($originalOptions);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('_completion')
            ->setDefinition($this->createDefinition())
            ->setDescription('Shell completion hook.')
            ->setHelp(<<<END
To enable shell completion, run:

    <comment>eval `[program] _completion -g`</comment>.

Or for an alias:

    <comment>eval `[program] _completion -g -p [alias]`</comment>.

END
            );

        // Hide this command from listing if supported
        // Command::setHidden() was not available before Symfony 3.2.0
        if (method_exists($this, 'setHidden')) {
            $this->setHidden(true);
        }
    }

    /**
     * Reduce the passed list of options to the core defaults (if they exist)
     *
     * @param InputOption[] $appOptions
     *
     * @return InputOption[]
     */
    protected function filterApplicationOptions(array $appOptions)
    {
        return array_filter($appOptions, function (InputOption $option) {
            static $coreOptions = array(
                'help' => true,
                'quiet' => true,
                'verbose' => true,
                'version' => true,
                'ansi' => true,
                'no-ansi' => true,
                'no-interaction' => true,
            );

            return isset($coreOptions[$option->getName()]);
        });
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handler = new CompletionHandler($this->getApplication());
        $handler = $this->handler;
        $shellType = $input->getOption('shell-type') ?: $this->getShellType();
        $factory = $this->resolveHookFactory($shellType);

        if ($input->getOption('generate-hook')) {
            global $argv;

            $program = $argv[0];
            $alias = $input->getOption('program');
            $multiple = (bool)$input->getOption('multiple');

            if (! $alias) {
                $alias = basename($program);
            }

            $hook = $factory->generateHook(
                $program,
                $alias,
                $multiple
            );

            $output->write($hook, true);
        } else {
            $handler->setContext(new EnvironmentCompletionContext());

            // Get completion results
            $results = $this->runCompletion();

            // Escape results for the current shell
            foreach ($results as &$result) {
                $result = $factory->escape($result, $this->handler->getContext());
            }

            unset($result);

            $output->write($results, true);
        }

        return 0;
    }

    /**
     * Run the completion handler and return a filtered list of results
     *
     * @return string[]
     * @deprecated - This will be removed in 1.0.0 in favour of CompletionCommand::configureCompletion
     *
     */
    protected function runCompletion()
    {
        $this->configureCompletion($this->handler);

        return $this->handler->runCompletion();
    }

    /**
     * Configure the CompletionHandler instance before it is run
     *
     * @param CompletionHandler $handler
     */
    protected function configureCompletion(CompletionHandler $handler)
    {
        // Override this method to configure custom value completions
    }

    /**
     * Determine the shell type for use with HookFactory
     *
     * @return string
     */
    protected function getShellType()
    {
        if (! getenv('SHELL')) {
            throw new \RuntimeException('Could not read SHELL environment variable. Please specify your shell type using the --shell-type option.');
        }

        return basename(getenv('SHELL'));
    }

    /**
     * @return InputDefinition
     */
    protected function createDefinition()
    {
        return new InputDefinition(array(
            new InputOption(
                'generate-hook',
                'g',
                InputOption::VALUE_NONE,
                'Generate BASH code that sets up completion for this application.'
            ),
            new InputOption(
                'program',
                'p',
                InputOption::VALUE_REQUIRED,
                "Program name that should trigger completion\n<comment>(defaults to the absolute application path)</comment>."
            ),
            new InputOption(
                'multiple',
                'm',
                InputOption::VALUE_NONE,
                'Generated hook can be used for multiple applications.'
            ),
            new InputOption(
                'shell-type',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set the shell type (zsh or bash). Otherwise this is determined automatically.'
            ),
        ));
    }

    /**
     * Resolves the hook factory
     *
     * @param string $shellType
     *
     * @return HookFactory
     */
    private function resolveHookFactory($shellType)
    {
        switch ($shellType) {
            case BashHookFactory::SHELL:
                return new BashHookFactory();

            case ZshHookFactory::SHELL:
                return new ZshHookFactory();

            default:
                throw new \RuntimeException("Cannot generate hook for unknown shell type '$shellType'. Available hooks are: bash, zsh");
        }
    }
}
