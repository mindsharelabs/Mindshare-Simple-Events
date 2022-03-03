const gulp = require('gulp');
const sass = require('gulp-sass')(require('node-sass'));
const sourcemaps = require('gulp-sourcemaps');
const del = require('del');


gulp.task('plugin-styles', () => {
    return gulp.src('sass/style.scss')
      .pipe(sourcemaps.init())
      .pipe(sass({
        outputStyle: 'compressed'//nested, expanded, compact, compressed
      }).on('error', sass.logError))
      .pipe(sourcemaps.write('./css/'))
      .pipe(gulp.dest('./css/'))
});



gulp.task('clean', () => {
    return del([
        'sass/style.css',

    ]);
});

gulp.task('watch', () => {
  gulp.watch('scss/*.scss', (done) => {
    gulp.series(['plugin-styles'])(done);
  });
});

gulp.task('default', gulp.series(['clean','plugin-styles', 'watch']));
