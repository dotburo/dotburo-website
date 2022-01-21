module.exports = function(grunt) {
    var path = require('path');

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        htmlmin: {
            dist: {
                options: {
                    removeComments: true,
                    collapseWhitespace: true
                },
                files: {
                    'dist/templates/index.html': 'resources/templates/index.html',
                    'dist/templates/page.html': 'resources/templates/page.html',
                    'dist/templates/grid.html': 'resources/templates/grid.html'
                }
            }
        },
        cssmin: {
            options: {
                processImport : true
            },
            concat: {
                files: {
                    'dist/css/index.min.css': ['resources/css/index.css']
                }
            }
        },
        requirejs: {
            concat : {
                options: {
                    baseUrl: 'resources/js',
                    mainConfigFile: 'resources/js/index.js',
                    name: path.resolve('node_modules/almond/almond.js'),
                    include: 'index.js',
                    optimize: 'uglify2',
                    out: 'dist/js/index.min.js',
                    wrap: true,
                    generateSourceMaps: true,
                    logLevel: 4,
                    preserveLicenseComments: false
                }
            }
        },
        watch: {
            scripts: {
                cwd: 'resources',
                files: ['**/*.html', '**/*.js', '**/*.css'],
                tasks: ['default']
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-htmlmin');
    grunt.loadNpmTasks('grunt-contrib-requirejs');

    grunt.registerTask('min:html', ['htmlmin']);
    grunt.registerTask('concat:css', ['cssmin:concat']);
    grunt.registerTask('concat:js', ['requirejs:concat']);

    grunt.registerTask('default', ['min:html','concat:css','concat:js']);
};