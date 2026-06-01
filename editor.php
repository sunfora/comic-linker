<!doctype html>

<?
  $directory = ".";
  $image_files = glob($directory . '/comic/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

  function create_images_ul($files, $class_name=null) {
    $list = "";
    foreach ($files as $file) {
      $list .= "<li> <img src=\"$file\"> </li>\n";
    }
    if ($class_name) {
      return "<ul class=\"$class_name\"> $list </ul>";
    } else {
      return "<ul> $list </ul>";
    }
  }
?>

<html>
  <head>
    <style>
      :root {
        --cell-size: 100px;
        --cell-border: 5px solid black;
        --selected-cell-border: 5px solid gold;
      }
      * {
        box-sizing: border-box;
      }
      
      .picker {
        border: 1px solid black;
        padding: 0;
        margin: 0;

        display: flex;
        align-items: center;

        li {
          margin: 20px;
          list-style: none;

          width: var(--cell-size);
          height: var(--cell-size);
          border: var(--cell-border);
          flex: none;
        }
        li.selected {
          border: var(--selected-cell-border);
        }


        &.edit {
          flex-direction: column;
          width: 150px;
          height: 500px;

          overflow-y: scroll;
          overflow-x: clip;
        }
        &.link {
          margin-left: 150px;
          width: 1000px;
          height: 150px;

          overflow-x: scroll;
          overflow-y: clip;
        }
      }
      .picker img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .image-viewer {
        display: flex;
        > * {
          flex: none;
        } 
        .view-container {
          display: flex;
          align-items: center;
          justify-content: center;
          background: grey;
          width: 500px;
          height: 500px;
        }
        img.view {
          max-width: 450px;
          max-height: 450px;
          object-fit: contain;
        }
        
        #spots-editor {
          position: absolute;
          width: 500px;
          height: 500px;
          border: 5px solid violet;
        }
      }
    </style>
  </head>
  <body>
    <div class="editor">
    <? echo create_images_ul($image_files, "picker link") ?>
      <div class="image-viewer">
        <? echo create_images_ul($image_files, "picker edit") ?>
        <div class="view-container">
          <img class="view edit" src="" alt="edit" width=500 height=500 />
          <canvas id="spots-editor"> </canvas>
        </div>
        <div class="view-container">
          <img class="view link" src="" alt="link"   width=500 height=500 />
        </div>
      </div>
    </div>

  <script>
      
      function add_indexes(dom_list) {
        let id = 0;
        for (const child of dom_list.children) {
          child.setAttribute('data-index', id);
          id += 1;
        }
      }
      
      /**
       * Get mathematical remainder. a = r mod b, where b > r >= 0
       * @param {number} a - the number to divide
       * @param {number} m - the modulo
       * @returns {number} - remainder of division
       */
      function rem(a, m) {
        return ((a % m) + m) % m;
      }

      /**
       * Function to linearly interpolate between two values
       * @param   {number} start - starting point
       * @param   {number} end   - the end point
       * @param   {number} t     - t ∈ [0,1], the percentage
       * @returns {number}       - the linearly interpolated value 
       */
      function lerp(start, end, t) {
        return start + t * (end - start);
      }

      /**
       * Return value if it is in the range between min_value and max_value.
       * @param {number} min_value
       * @param {number} value
       * @param {number} max_value
       */
      function clamp(min_value, value, max_value) {
        return Math.min(Math.max(min_value, value), max_value);
      }
    
      const select_edit = document.querySelector(".picker.edit");
      const select_link = document.querySelector(".picker.link");

      const edit_view = document.querySelector(".view.edit");
      const link_view = document.querySelector(".view.link");

      const spots_editor = document.querySelector("#spots-editor");
      const ctx = spots_editor.getContext("2d");

      select_edit.children[0].classList.add("selected");
      select_link.children[0].classList.add("selected");

      add_indexes(select_edit);
      add_indexes(select_link);

      update_image(select_edit);
      update_image(select_link);

      function update_image(container) {
        const img = container.querySelector(".selected img");
        if (container === select_edit) {
          edit_view.setAttribute("src", img.getAttribute("src"));
        } else {
          link_view.setAttribute("src", img.getAttribute("src"));
        }
      }

      const update_selected = (callback) => (event) => {
        const container = event.currentTarget;
        let user_clicked_on = event.target;

        let not_reached_proper_element = true;
        while (not_reached_proper_element) {
          if (user_clicked_on === container) {
            return;
          }
          if (user_clicked_on.tagName === "LI") {
            not_reached_proper_element = false;
          } else { 
            user_clicked_on = user_clicked_on.parentElement;
          }
        }

        const current_selected = container.querySelector(".selected");
        current_selected.classList.remove("selected");
        user_clicked_on.classList.add("selected");
        update_image(container);

        callback(user_clicked_on);
      }

      let game = {
        pictures_count:          0,
        selected_lnk:            0,
        selected_src:            0,
        current_pair:            0,
        clientX:                 0,
        clientY:                 0,
        width:                   0,
        height:                  0,
        unit:                    0,
        precision:               0,
        angle:                   0,
        time:                    0,
        progress:                0,

        precision_mode:       false,
        src_changed:          false,
        lnk_changed:          false,
        user_is_on_tab:       false,

        shapes:               new Map(),

        cursor: {
          x:             0,
          y:             0,
          size:          0,
          display_x:     0,
          display_y:     0,
          display_size:  0,
          hold_acc:      0,
          hold_velocity: 0,
          holding:       0,
          acc_rate:      0,
          zoom_velocity: 0,

          down:          false,
        },

        settings: {
          wheel_speed:          0,
          precision_default:    0,
          precision_mode_value: 0,
        },

        get time_updated()  {
          return Date.now();
        },
        get current_pair_updated() {
          return this.selected_lnk + this.selected_src * this.pictures_count;
        },
        get pictures_count_updated() {
          return select_edit.children.length;
        },
        get unit_updated() {
          return Math.min(this.width, this.height) / 100;
        },
        get progress_updated() {
          return this.cursor.holding / (2 * Math.PI * this.cursor.display_size);
        }
      };
      
      spots_editor.width  = 500;
      spots_editor.height = 500;

      // NOTE(ivan): initialize user settings
      {
        game.settings.wheel_speed          = 1;
        game.settings.precision_default    = 1;
        game.settings.precision_mode_value = 10;
      }
      
      // NOTE(ivan): initialize game window
      {
        const {x: g_x, y: g_y, width: g_w, height: g_h} = spots_editor.getBoundingClientRect();
        game.clientX = g_x;
        game.clientY = g_y;

        game.width = g_w;
        game.height = g_h;

        game.unit = game.unit_updated;
      }


      // NOTE(ivan): initialize stamping tool
      {
        game.precision_mode       = false;
        game.cursor.down          = false;

        game.cursor.size          = game.unit * 10;
        game.cursor.display_size  = game.unit * 10;

        game.cursor.hold_acc      = game.unit / 10000;
        game.cursor.hold_velocity = 0;
        game.cursor.holding       = 0;

        game.cursor.acc_rate      = 0.05;
        game.cursor.zoom_velocity = 0;

        game.precision            = game.settings.precision_default;
      }

      // NOTE(ivan): initialize selected
      {
        game.pictures_count = game.pictures_count_updated;
        game.selected_lnk   = 0;
        game.selected_src   = 0;
        game.current_pair   = game.current_pair_updated;

        game.src_changed = true;
        game.lnk_changed = true;
      }

      edit_view.addEventListener("load", () => { game.src_changed = true; })
      link_view.addEventListener("load", () => { game.lnk_changed = true; })

      select_edit.addEventListener("click", update_selected((li)  => {
        game.selected_lnk = Number.parseInt(li.getAttribute('data-index'));
      }));
      select_link.addEventListener("click", update_selected((li) => {
        game.selected_src = Number.parseInt(li.getAttribute('data-index'));
      }));

      spots_editor.addEventListener("mouseover", (event) => { 
        game.cursor.x = event.clientX - game.clientX; 
        game.cursor.y = event.clientY - game.clientY; 
        game.track_user = true;
      });
      spots_editor.addEventListener("mouseleave", (event) => { 
        game.cursor.x = event.clientX - game.clientX; 
        game.cursor.y = event.clientY - game.clientY; 
        game.cursor.down = false; 
        game.track_user = false;
      });
      spots_editor.addEventListener("mousemove", (event) => {
        if (game.track_user) {
          game.cursor.x = event.clientX - game.clientX; 
          game.cursor.y = event.clientY - game.clientY; 
        }  
      });

      spots_editor.addEventListener("wheel", (event) => {
        event.preventDefault();

        let precision = game.precision;
        
        if (game.track_user) {
          game.cursor.size = clamp(
            game.unit * 5, 
            game.cursor.size + (event.deltaY / game.unit) / precision, 
            game.unit * 80
          );
        }
        if (event.deltaMode !== 0) {
          debugger;
        }
      })
      
      game.time           = game.time_updated;
      game.angle          = 0;
      game.user_is_on_tab = true;

      game.debug_entry = function () {
        if (this.debug_mode) {
          debugger;
        }
      }

      document.addEventListener("keyup", (e) => {
        if (e.key === 'Shift') {
          game.precision_mode = false;
        }
      });

      document.addEventListener("keydown", (e) => {
        if (e.key.toLowerCase() === "d") {
          game.debug_mode = true;
        } else if (e.key === "Escape") {
          game.debug_mode = false;
        } else if (e.key === 'Shift') {
          game.precision_mode = true;
        }
      });

      spots_editor.addEventListener("mousedown", (e) => {
        e.preventDefault();
        game.cursor.down = true;        
      });
      document.addEventListener("mouseup", (e) => {
        game.cursor.down = false;        
      });

      const star_image = new Image();
      star_image.setAttribute("src", "./editor-assets/star.png");
      let star_time = null;
      let star_duration = 200;

      // stamp drawing
      function draw_stamp(x, y, size) {
        ctx.translate(x, y);
        ctx.beginPath();
        let stamp_radius = size;
        ctx.arc(0, 0, stamp_radius, 0, 2 *  Math.PI);
        ctx.fillStyle = "#facadeA0";
        ctx.fill();
        ctx.translate(-x, -y);
      }

      const CurrentShapeThresholds = {
        DO_NOTHING: 0,
        ADD: 1,
      };
      

      //
      // Entrypoint
      //
      function loop() {
        ctx.clearRect(0, 0, spots_editor.width, spots_editor.height);

        // draw the shapes
        ctx.save();
        {
          const shapes = game.shapes.get(game.current_pair);
          if (shapes) {
            for (const shape of shapes) {
              draw_stamp(shape.x, shape.y, shape.size);
            }
          }
        }
        ctx.restore();

        // draw the stamp tool
        ctx.save();
        {
          const progress   = game.progress;

          const stamp_x    = game.cursor.display_x;
          const stamp_y    = game.cursor.display_y;
          let stamp_radius = game.cursor.display_size;

          if (game.cursor.down) {
            stamp_radius -= 4;
          }

          draw_stamp(stamp_x, stamp_y, stamp_radius);

          ctx.translate(game.cursor.display_x, game.cursor.display_y);
          
          const before_spin = 6;
          const spin_forward = 9; 
          if (progress > before_spin) {
            if (progress < spin_forward) {
              ctx.rotate(-(progress - before_spin) * Math.PI / 12);
            } else {
              const start_angle = -(spin_forward - before_spin) * Math.PI / 12
              ctx.rotate(start_angle + (progress - spin_forward) * Math.PI / clamp(3, -lerp(-12, -3, Math.sqrt(progress - spin_forward)), 12));
            }
          }


          let cube_size = game.cursor.display_size;
          if (!game.cursor.down) {
            cube_size *= 1.2; 
            cube_size += game.unit * 5 * 0.1 * Math.sin(8 * game.angle);
          } else if (progress < 1) {
            cube_size = clamp(cube_size * 0.95, cube_size * (1 - progress), cube_size);
          } else if (progress > 1) {
            cube_size = clamp(cube_size, cube_size * progress * progress * progress, cube_size * 1.2 + game.unit / 2);
          }

          
          if (game.cursor.down) {
            ctx.beginPath();
            const holding_angle = game.cursor.holding / stamp_radius;
            ctx.strokeStyle = "darkpurple";
            ctx.lineWidth = 4;
            ctx.arc(0, 0, stamp_radius + 2, 0, holding_angle);
            ctx.stroke();
          }

          ctx.lineWidth = 2;
          ctx.strokeStyle = "black";
          
          for (let i = 0; i < 4; ++i) {
            ctx.save();
            ctx.rotate(i * Math.PI / 2);
            game.debug_entry();
            const begin_x = -cube_size;
            const begin_y = -cube_size;
            const size = game.unit * 10;
            const clip_region_radius = size * 0.3;

            ctx.beginPath();
            ctx.rect(begin_x - clip_region_radius, begin_y - clip_region_radius, clip_region_radius * 2, clip_region_radius * 2);
            // ctx.fillStyle = ["orange", "green", "red", "blue"][i];
            // ctx.fill();
            ctx.clip();
            ctx.beginPath();
            ctx.roundRect(begin_x, begin_y, 2 * size, 2 * size, size / 8);
            ctx.stroke(); 
            ctx.restore();
          }
          
          if (progress > 1) {
            
            if (star_time === null) {
              star_time = game.time;
            }
            let star_progress = 1 - (game.time - star_time) / star_duration;
            let size = Math.max(0, lerp(0, 2 * cube_size, star_progress));
            ctx.drawImage(star_image, -size/2, -size/2,  size, size);
            game.shape += 1;
          } else {
            game.shape = 0;
            star_time = null;
          }
        }
        ctx.restore();
        
        // update the state
        {
          const time_updated = game.time_updated;
          game.dt   = time_updated - game.time;
          game.time = time_updated; 

          const dt = game.dt;

          // NOTE(ivan): this adds the illusion of smooth scroll between different browsers
          //             if you rely on browser's wheel event 
          {
            const actual_size   = game.cursor.size;
            const acc_rate      = game.cursor.acc_rate;
            let display_size    = game.cursor.display_size;
            let zoom_velocity   = game.cursor.zoom_velocity;


            if (display_size < actual_size) {
              zoom_velocity += acc_rate * dt
              display_size = clamp(display_size, display_size + zoom_velocity, actual_size);
            } else if (display_size > actual_size) {
              zoom_velocity += game.cursor.acc_rate * dt
              display_size = clamp(actual_size, display_size - zoom_velocity, display_size);
            } else {
              zoom_velocity = 0;
            }

            game.cursor.display_size  = display_size;
            game.cursor.zoom_velocity = zoom_velocity;
          }

          game.angle += 0.001 * dt;
          game.angle %= 2 * Math.PI;

          if (game.cursor.down) {
            game.cursor.hold_velocity += game.cursor.hold_acc * dt;

            if (game.cursor.holding === 0) {
              game.cursor.holding_x    = game.cursor.x;
              game.cursor.holding_y    = game.cursor.y;
              game.cursor.holding_size = game.cursor.size;
            }
            game.cursor.holding += game.cursor.hold_velocity * dt;
          } else {
            game.cursor.holding = 0;
            game.cursor.hold_velocity = 0;
            game.cursor.holding_x    = 0;
            game.cursor.holding_y    = 0;
            game.cursor.holding_size = 0;
            game.cursor.display_x = game.cursor.x;
            game.cursor.display_y = game.cursor.y;
          }

          game.progress = game.progress_updated;

          if (game.src_changed) {
            game.src = edit_view.getAttribute("src");
            game.current_pair = game.current_pair_updated;
            game.src_changed = false;
          }
          if (game.lnk_changed) {
            game.lnk = link_view.getAttribute("src");
            game.current_pair = game.current_pair_updated;
            game.lnk_changed = false;
          }

          if (game.precision_mode) {
            game.precision = game.settings.precision_default * game.settings.precision_mode_value;
          } else {
            game.precision = game.settings.precision_default;
          }

          if (game.shape == CurrentShapeThresholds.ADD) { 
            const shapes = game.shapes.getOrInsertComputed(game.current_pair, () => []); 
            shapes.push({
              x:    game.cursor.holding_x, 
              y:    game.cursor.holding_y,
              size: game.cursor.holding_size
            });
          }
        }

        if (game.user_is_on_tab) {
          requestAnimationFrame(loop);
        }
      }
      
      document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
          game.user_is_on_tab = false;
        } else {
          game.user_is_on_tab = true;
          requestAnimationFrame(loop);
        }
      });
      requestAnimationFrame(loop);
    </script>
  </body
</html>
