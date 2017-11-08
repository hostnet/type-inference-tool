# Type-inference-tool
As of PHP 7.0 it is possible to add [scalar type hints](https://wiki.php.net/rfc/scalar_type_hints) and
[return type declarations](https://wiki.php.net/rfc/return_types). The type-inference-tool is created to add these
type hints to PHP-applications by executing one command.

The type-inference-tool adds parameter- and return type hints to PHP-projects by using dynamic and static analysis.
Dynamic analysis is done by analyzing traces generated during the execution of the target project its PHPUnit tests
(runtime). Static analysis is done by parsing the PHP-code of the target project to abstract syntax tree's and
analysing them. The data collected by the static- and dynamic analyzers are combined and used to infer parameter-
and function types.


## Prerequisites
* XDebug must be installed
* Requires PHP 7.1+
* Target project must have working PHPUnit tests (non-failing/not causing errors)
* Target project its Composer dependencies must be installed

## Installation
Clone `hostnet/type-inference-tool` and install the dependencies using Composer.

## Usage
The type-inference-tool is used by executing a command. This section explains the available options for this command.

__Base command:__

The most basic command to execute is the following:
```
./application.php execute <target project directory>
```

__Options:__

Certain options can be set alongside the base command. These are the following:

* `--log-dir=<path>`: Specify a file to write logs to. The progress during execution can be followed by tailing the
log file. Cases in which type hints cannot be added to parameters or functions will also be logged. 
* `--storage-type=<mem|file|db>`: The dynamic analyzer uses
[XDebug trace files](https://xdebug.org/docs/execution_trace) during analysis. Parsing these traces can take up a lot
of space, depending on the size of the target project. To prevent the tool from exhausting the memory, three storage
methods are provided: `mem` (default), `file` and `db`. `mem` will use the internal memory to save the parsed data to.
This is usually the fastest method, but not recommended when running the tool on bigger projects. `file` will store the
parsed data to external files, this results in less memory usage but a slower execution time. `db` is similar to `file`
but will store data to a database. When running the tool on huge projects, `db` is recommended. Note that when using
`db`, the following option `--db-config` must be provided.
* `--db-config=<db-config.json>`: When using `--storage-type=db` this option must be provided. With this option a
database configuration is set. See [/database/config.json.dist](/database/config.json.dist) for the structure of
such configuration file. Make sure your database has same structure as defined in [DDL.sql](/database/config.json.dist)
* `--ignore-folders=<folder1,folder2,etc>`: Set folders to be ignored. Files within ignored folder will not receive
type hints. The vendor-folder is always ignored. This option is especially useful to exclude generated files from
being altered.
* `--trace=<existing_trace.xt>`: In case you already have an XDebug trace file for your target project, you can provide
that trace file to be used during dynamic analysis. This is profitable for large projects where generating XDebug trace
files can take quite some time and space.
* `--show-diff`: When enabled, the type-inference-tool will output all changes made (diffs) to the target project on
the console.
* `--analyse-only`: By enabling this option the type-inference-tool will not modify the given target project. This is
useful in combination with the `--show-diff` option for small projects to check what type hints would be added.
* `--help`: Shows help. Lists all available options with short descriptions.

##Examples
This section provides some execution command examples. These examples are classified in three types of target
projects: small, bigger and huge. Since these terms are quite subjective it is up to you to determine whether your
target projects would be small, big or huge.

__Small projects__

When executing the tool on 'smaller' projects, the command below would be recommended. Using this command the in-memory
data storage would be used.
```
./application.php execute /home/user/projects/my-project --log-dir=/home/user/projects/logs/mylogs.log --ignore-folders=Generated,cache,test
```
__Bigger projects__

When executing the project on a 'bigger' project, the file storage type would be recommended. When executing the tool
on 'bigger' or 'huge' projects it would be profitable to carefully set your `--ignore-folders` as this would decrease
the execution time of the tool.
```
./application.php execute /home/user/projects/my-project --log-dir=/home/user/projects/logs/mylogs.log --storage-type=file --ignore-folders=Generated,cache,test
```

__Huge projects__

When executing the tool on huge projects, the database storage type would be recommended. Important is that your
database config (`--db-config`) is correct and your database already has the valid tables (database definition
provided in [DDL.sql](/database/config.json.dist)). Note that executing the tool on 'huge' projects easily can
take up to more than one hour (depending on the size of the target project). Providing an existing XDebug trace would
decrease the execution time. If no trace is provided, the type-inference-tool generates a new one. The newly generated
trace file will be deleted afterwards.

```
./application.php execute /home/user/projects/my-project --log-dir=/home/user/projects/logs/mylogs.log --storage-type=db --db-config=/home/user/database-config.json --ignore-folders=Generated,cache,test
```

## After execution
The type-inference-tool does not guarantee that the resulting target project is entirely correct. Make sure you verify
the state of your target project. This could be done by executing PHPUnit and checking whether no failures or errors
occur.

The type-inference-tool does not add use-statements to project files, after adding type hints you may have to
add use-statements yourself.

The type-inference-tool may cause violations to your PHP-coding style/conventions. Make sure you check this by hand or
using a PHP code-sniffer. A violation the tool may cause is that certain lines of code exceed the maximum amount of
characters per line after adding type hints.

## Improvements
The type-inference-tool currently has following limitations:
* The tool does not check for [PHP 7.2 covariance](https://wiki.php.net/rfc/parameter-no-type-variance)
