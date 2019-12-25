module.exports = function(grunt) {
    'use strict';
    require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        sass: {
            dist: {
                files: {
                    'style.css' : 'sass/style.scss'
                },
                options: {
                    style: 'compressed',
                }
            }
        },

        watch: {
            configFiles: {
                files: [ 'GRUNTFILE.js' ], //tasks: ['newer:jshint:all'],
                options: {
                    reload: true
                }
            },

            sass: {
                files: [
                    'sass/*.scss',
                    'sass/bootstrap/*.scss'
                ],
                tasks: [
                    'sass'
                ]
            }
        }
    });
    grunt.registerTask('default', [ 'watch' ]);
};
