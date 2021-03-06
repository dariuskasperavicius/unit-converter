<?php

declare(strict_types = 1);

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
use Dotenv\Dotenv;
use Robo\Tasks;

class RoboFile extends Tasks
{
    const CHANGELOG_BIN = 'github_changelog_generator';

    const CHANGELOG_COMMIT_MESSAGE = 'Update changelog for v';

    const CHANGELOG_FILE = 'CHANGELOG.md';

    const DOCUMENATION_COMMIT_MESSAGE = 'Update documentation for v';

    const DOCUMENTATION_BIN = 'bin/phpdoc.phar';

    const DOCUMENTATION_ROOT = 'docs';

    /**
     * Generate a new copy of the changelog.
     *
     * @link https://github.com/skywinder/github-changelog-generator
     *
     * @return void
     */
    public function generateChangelog()
    {
        $bin = self::CHANGELOG_BIN;
        $exitCode = $this->_exec($bin)->getExitCode();

        switch ($exitCode) {
            case 0:
                $message = '<info>Successfully regenerated changelog</info>';

                break;
            case 1:
                $message = "<fg=red>An error ({$exitCode}) occured while generating the changelog</>";

                break;
            case 127:
                $message = '<fg=red>Cannot find <fg=red;options=bold>%s</>! Are you sure it is installed?</>';

                break;
            default:
                throw new Exception("An unknown error ({$exitCode}) occured while generating the changelog");
        }

        $this->say(sprintf($message, $bin));
    }

    /**
     * Generate a fresh set of documentation.
     *
     * @param null|string $source The source to generate the documentation from.
     * @param null|string $destination The destination to output the generated files.
     * @return void
     */
    public function generateDocs(string $source = null, string $destination = null): void
    {
        $destination = $this->documentationRoot($destination);

        if (file_exists($destination) and is_dir($destination)) {
            $this->say('<fg=red>Removing stale documentation .. </>');
            $this->taskDeleteDir($destination)->run();
            $this->say('<info>OK</info>');
        } else {
            $this->say('<comment>No stale documentation to remove</comment>');
        }

        $root = $this->rootPath();
        $bin = self::DOCUMENTATION_BIN;
        $generator = "{$root}/{$bin}";

        if (file_exists($generator)) {
            $source = $source ?? "{$root}/src/";
            $this->taskExec($generator)
                ->arg('run')
                ->option('directory', $source)
                ->option('target', $destination)
                ->run();
        } else {
            $this->io()->error("phpdoc.phar could not be found in {$generator}");

            exit;
        }
    }

    /**
     * Release the next stable version of the package.
     *
     * @param string $version A valid semver version scheme string to set as the next version and tag.
     * @param bool $commit Should the changes be commited and tagged?
     * @return void
     */
    public function releaseStable(string $version, bool $commit = true): void
    {
        $this->checkoutMaster($commit);
        $this->tagRelease($version, $commit);
        $this->pushAll($version, $commit);
        $this->upgradeDocumentation($version, $commit);
        $this->upgradeChangelog($version, $commit);
        $this->removeTag($version, $commit);
        $this->tagRelease($version, $commit);
        $this->pushAll($version, $commit);

        echo PHP_EOL;
        $this->say("Successfully bumped version to <fg=blue>v</><fg=cyan>{$version}</>");
    }

    /**
     * Remove a specified tag.
     *
     * @param string $version
     * @param boolean $commitTag
     * @return void
     */
    public function removeTag(string $version, bool $commitTag = true)
    {
        if ($commitTag) {
            $env = (new Dotenv(__DIR__))->load();
            $user = getenv('GITHUB_USERNAME');
            $oauthToken = getenv('GITHUB_OAUTH_TOKEN');
            $repository = getenv('GITHUB_REPOSITORY');
            $baseUrl = getenv('GITHUB_API_URL');
            $endpoint = "repos/{$user}/{$repository}/git/refs/tags/v{$version}";
            $curl = "curl -X DELETE -i {$baseUrl}/{$endpoint} -u {$user}:{$oauthToken}";

            $exitCode = $this->_exec($curl)->getExitCode();
            $this->_exec('git tag -d v'.$version);
        }
    }

    /**
     * Tag the current state of the repository.
     *
     * @param string $version The name of the tag.
     * @param boolean $commitTag (optional) Commit the tag or not.
     * @return void
     */
    public function tagRelease(string $version, bool $commitTag = true)
    {
        if ($commitTag) {
            $this->taskGitStack()
                ->stopOnFail()
                ->tag('v'.ltrim($version, 'v'), 'Tag release for v'.$version)
                ->run();
        }
    }

    /**
     * Checkout the master branch, and throw an exception if the checkout fails.
     *
     * @throws Exception If the git checkout cannot be completed
     * @param boolean $commit (optional) Will master _actually_ be checked out.
     * @return int
     */
    private function checkoutMaster(bool $commit = true): int
    {
        $exitCode = 0;

        if ($commit) {
            $exitCode = $this->taskGitStack()
                ->stopOnFail()
                ->checkout('master')
                ->run()
                ->getExitCode();

            if ((bool) $exitCode) {
                throw new Exception("You seem to have uncommited changes");
            }
        }

        return $exitCode;
    }

    /**
     * Returns the root path for the documentation to be generated in.
     *
     * @param null|string (optional) A custom path to generate the docs to.
     * @return string
     */
    private function documentationRoot(string $destination = null): string
    {
        $root = self::DOCUMENTATION_ROOT;

        return ($destination)
            ? rtrim($destination, '/').'/'.$root
            : "{$this->rootPath()}/{$root}";
    }

    /**
     * Helper function to push all to remote  origin.
     *
     * @param string $version
     * @param boolean $commit (optinoal) Is the push actually going to be performed?
     * @return integer
     */
    private function pushAll(string $version, bool $commit = true): int
    {
        $exitCode = 0;

        if ($commit) {
            $exitCode = $this->taskGitStack()
                ->stopOnFail()
                ->push('origin', 'master')
                ->push('origin', 'v'.$version)
                ->run()
                ->getExitCode();

            if ((bool) $exitCode) {
                throw new Exception("An error occured while trying to push to remote origin");
            }
        }

        return $exitCode;
    }

    /**
     * Returns the current root path of the repository.
     *
     * @return string
     */
    private function rootPath(): string
    {
        return rtrim(realpath(__DIR__), '/');
    }

    /**
     * Upgrade an asset for the next version of the project.
     *
     * @param string $method The name of the method to be used for upgrading.
     * @param string $files The files to add from the method changes
     * @param string $commitMessage The message to commit the changes with.
     * @param boolean $commitChanges (optional) If set to false, a commit will not be executed. Useful for dry runs.
     * @return void
     */
    private function upgradeAsset(string $method, string $files, string $commitMessage, bool $commitChanges = true): void
    {
        if (method_exists($this, $method)) {
            $this->{$method}();

            if ($commitChanges) {
                $this->taskGitStack()
                    ->stopOnFail()
                    ->add("{$this->rootPath()}/{$files}")
                    ->commit("{$commitMessage}")
                    ->run();
            }
        }
    }

    /**
     * Commit an upgrade to the changelog for the project.
     *
     * @param string $version
     * @param boolean $commit
     * @return void
     */
    private function upgradeChangelog(string $version, bool $commit = true): void
    {
        $changelog = self::CHANGELOG_FILE;
        $message = self::CHANGELOG_COMMIT_MESSAGE.$version;
        $this->upgradeAsset('generateChangelog', $changelog, $message, $commit);
    }

    /**
     * Commit an upgrade to the documentation for the project.
     *
     * @param string $version
     * @param boolean $commit
     * @return void
     */
    private function upgradeDocumentation(string $version, bool $commit = true): void
    {
        $documentation = self::DOCUMENTATION_ROOT;
        $message = self::DOCUMENATION_COMMIT_MESSAGE.$version;
        $this->upgradeAsset('generateDocs', $documentation, $message, $commit);
    }
}
