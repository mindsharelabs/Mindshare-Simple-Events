import gulp from "gulp";

import dartSass from 'sass'
import gulpSass from 'gulp-sass'
const sass = gulpSass(dartSass);

import sourcemaps from 'gulp-sourcemaps';
import {deleteAsync} from 'del';



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
    return deleteAsync([
        'sass/style.css',

    ]);
});

gulp.task('watch', () => {
  gulp.watch('sass/*.scss', (done) => {
    gulp.series(['plugin-styles'])(done);
  });
});

gulp.task('default', gulp.series(['clean','plugin-styles', 'watch']));





