module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        stylesheets: [
            'css/bootstrap.css',
            'css/bootstrapValidator.css',
            'css/backgrid-paginator.css',
            'css/jquery.fileupload.css',
            'css/fonts.css',
            'css/honeysens.css'],
        clean: ['dist'],
        mkdir: {
            dist: {
                options: { create: [
                    'dist/cache',
                    'dist/data/upload',
                    'dist/data/firmware',
                    'dist/data/configs',
                    'dist/data/CA'] }
            }
        },
        copy: {
            static: {
                expand: true,
                cwd: 'static/',
                dest: 'dist/public/',
                src: ['fonts/**', 'images/**', 'docs/**', '.htaccess']
            },
            app: {
                expand: true,
                dest: 'dist/',
                src: ['app/**', '!app/index.php']
            },
            public: {
                expand: true,
                cwd: 'app/',
                dest: 'dist/public/',
                src: ['index.php']
            },
            data: {
               files: [
                   { expand: true, cwd: 'utils/example-ca', dest: 'dist/data/CA/', src: '**' },
                   { expand: true, cwd: 'utils/', dest: 'dist/utils/', src: ['doctrine-cli.php', 'docker/**'] },
                   { expand: true, cwd: 'conf/', dest: 'dist/data/', src: 'config.clean.cfg' }
               ]
            },
            beanstalk: {
                expand: true,
                cwd: 'utils/',
                dest: 'dist/',
                src: 'beanstalk/**'
            },
            requirejs: {
                expand: true,
                cwd: 'js/',
                dest: 'dist/public/js/',
                src: 'lib/require.js'
            },
            js: {
                expand: true,
                cwd: 'js/',
                dest: 'dist/public/js/',
                src: '**'
            },
            docs: {
                files: [
                    { expand: true, cwd: 'dist/docs/admin_manual/', dest: 'dist/public/docs/', src: 'admin_manual.pdf' },
                    { expand: true, cwd: 'dist/docs/user_manual/', dest: 'dist/public/docs/', src: 'user_manual.pdf' }
                ]
            }
        },
        concat: {
            dist: {
                dest: 'dist/public/css/<%= pkg.name %>.css',
                src: '<%= stylesheets %>'
            }
        },
        requirejs: {
            dist: {
                options: {
                    baseUrl: 'js/lib',
                    mainConfigFile: 'js/main.js',
                    out: 'dist/public/js/main.js',
                    name: 'app/main',
                    wrapShim: true
                }
            }
        },
        cssmin: {
            dist: {
                files: { 'dist/public/css/<%= pkg.name %>.css': '<%= stylesheets %>' }
            }
        },
        latex: {
            admin_manual: {
                options: {
                    outputDirectory: 'dist/docs/admin_manual'
                },
                expand: true,
                cwd: 'docs/admin_manual/',
                src: 'admin_manual.tex'
            },
            user_manual: {
                options: {
                    outputDirectory: 'dist/docs/user_manual'
                },
                expand: true,
                cwd: 'docs/user_manual/',
                src: 'user_manual.tex'
            }
        },
        chmod: {
            scripts: {
                options: {
                    mode: '755'
                },
                src: ['dist/app/scripts/**', 'dist/utils/docker/*.sh', 'dist/utils/docker/services/*/run']
            },
            data: {
                options: {
                    mode: '777'
                },
                src: [
                    'dist/cache',
                    'dist/data/configs',
                    'dist/data/firmware',
                    'dist/data/upload',
                    'dist/data/config.clean.cfg',
                    'dist/data']
            }
        },
        watch: {
            app: {
                files: ['app/**'],
                tasks: ['copy:app'],
                options: { spawn: false }
            },
            js: {
                files: ['js/**'],
                tasks: ['copy:js'],
                options: { spawn: false }
            },
            css: {
                files: '<%= stylesheets %>',
                tasks: ['concat:dist'],
                options: { spawn: false }
            }
        }
    });

    // only work on updated files
    var changedAppFiles = Object.create(null),
        onAppChange = grunt.util._.debounce(function(path) {
            grunt.config('copy.app.src', Object.keys(changedAppFiles));
            changedAppFiles = Object.create(null);
        }, 200),
        changedJSFiles = Object.create(null),
        onJSChange = grunt.util._.debounce(function(path) {
            grunt.config('copy.js.src', Object.keys(changedJSFiles));
            changedJSFiles = Object.create(null);
        });
    grunt.event.on('watch', function(action, filepath) {
        if(grunt.file.isMatch('app/**', filepath)  ) {
            if(!grunt.file.isMatch('app/index.php', filepath)) {
                changedAppFiles[filepath] = action;
            }
            onAppChange();
        } else if(grunt.file.isMatch('js/**', filepath)) {
            changedJSFiles[filepath.slice(3)] = action;
            onJSChange();
        }
    });

    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-mkdir');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-requirejs');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-chmod');
    grunt.loadNpmTasks('grunt-simple-watch');
    grunt.loadNpmTasks('grunt-latex');

    grunt.registerTask('docs', [
        'latex',
        'latex', // Invoke pdflatex a second time for indexing and layouting
        'copy:docs'
    ]);
    grunt.registerTask('default', [
        'clean',
        'mkdir',
        'copy',
        'concat',
        'chmod'
    ]);
    grunt.registerTask('release', [
        'clean',
        'mkdir',
        'docs',
        'copy:static',
        'copy:app',
        'copy:public',
        'copy:data',
        'copy:beanstalk',
        'copy:requirejs',
        'requirejs',
        'cssmin',
        'chmod'
    ]);
};
