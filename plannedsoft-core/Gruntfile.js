module.exports = function(grunt) {
  'use strict';

  var saVersion = '';
  var pkgJson = require('./package.json');

  require('matchdep').filterDev('grunt-*').forEach( grunt.loadNpmTasks );

  grunt.getPluginVersion = function() {
    var p = 'plannedsoft-core.php';
    if (saVersion == '' && grunt.file.exists(p)) {
        var source = grunt.file.read(p);
        var found = source.match(/Version:\s(.*)/);
        saVersion = found[1];
    }
    return saVersion;
  };

  grunt.initConfig({
    pkg: '<json:package.json>',
      compress: {
          main: {
              options: {
                archive: '../plannedsoft-core.v' + pkgJson.version + '.zip'
              },
              files: [
                { src: 'assets/**', dest: 'plannedsoft-core/' },
                { src: 'includes/**', dest: 'plannedsoft-core/' },
                { src: 'languages/**', dest: 'plannedsoft-core/' },
                { src: 'index.php', dest: 'plannedsoft-core/' },
                { src: 'plannedsoft-core.php', dest: 'plannedsoft-core/' },
                { src: 'phpcs.xml', dest: 'plannedsoft-core/' }
              ]
          }
      },
  		'string-replace': {
  			inline: {
          files: {
            './': ['plannedsoft-core.php']
  				},
  				options: {
  					replacements: [
              {
                pattern: 'Version: ' + grunt.getPluginVersion(),
  							replacement: 'Version: ' + pkgJson.version
  						}, {
                pattern: 'define\( \'plannedsoft_core_VERSION\', \'' + grunt.getPluginVersion() + '\' );',
  							replacement: 'define\( \'plannedsoft_core_VERSION\', \'' + pkgJson.version + '\' );'
  						}
  					]
  				}
  			}
  		},
      http_upload: {
        local: {
          options: {
            url: 'http://industrialmatrix.local/wp-json/plannedsoft-server-admin/v1/plugins/5/versions/',
            method: 'POST',
            rejectUnauthorized: false,
            headers: {
              'Content-Type': 'multipart/form-data'
            },
            data: {
              api_key : 'NUt3plqvGQXbmIllsebEAm0duRGD9De1',
              version : pkgJson.version,
              requires : '5.4.2',
              tested : '5.4.3',
              requires_php : '5.6',
              changelog: '',
              upgrade_notice: '',
              status: 'public',
            },
            onComplete: function(data) {
              console.log('Response: ' + data);
            }
          },
          src: '../plannedsoft-core.v' + pkgJson.version + '.zip',
          dest: 'file'
        },
        server: {
          options: {
            url: 'https://download.plannedsoft.com/wp-json/plannedsoft-server-admin/v1/plugins/1/versions/',
            method: 'POST',
            rejectUnauthorized: false,
            headers: {
              'Content-Type': 'multipart/form-data'
            },
            data: {
              api_key : 'On5CSp312KSTfxxtHcAMkGlPD4nzlywt',
              version : pkgJson.version,
              requires : '5.4.2',
              tested : '5.4.3',
              requires_php : '5.6',
              changelog: '',
              upgrade_notice: '',
              status: 'public',
            },
            onComplete: function(data) {
              console.log('Response: ' + data);
            }
          },
          src: '../plannedsoft-core.v' + pkgJson.version + '.zip',
          dest: 'file'
        }
      }
  });

  //grunt.registerTask('translate', [ 'makepot' ]);
  //grunt.registerTask('version', [ 'string-replace' ]);
  grunt.registerTask('build', [ 'string-replace', 'compress' ]);
  grunt.registerTask('deploy-local', [ 'build', 'http_upload:local' ]);
  grunt.registerTask('deploy-server', [ 'build', 'http_upload:server' ]);
};
