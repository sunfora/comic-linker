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

      let game = {};
      game.pictures_count = select_edit.children.length;
      game.selected_lnk = 0;
      game.selected_src = 0;
      game.current_pair = game.selected_lnk + game.selected_src * game.pictures_count;

      select_edit.addEventListener("click", update_selected((li)  => {
        game.selected_lnk = Number.parseInt(li.getAttribute('data-index'));
      }));
      select_link.addEventListener("click", update_selected((li) => {
        game.selected_src = Number.parseInt(li.getAttribute('data-index'));
      }));

      spots_editor.width = 500;
      spots_editor.height = 500;

      {
        const {x: g_x, y: g_y, width: g_w, height: g_h} = spots_editor.getBoundingClientRect();
        game.clientX = g_x;
        game.clientY = g_y;

        game.width = g_w;
        game.height = g_h;

        game.unit = Math.min(game.width, game.height) / 100;
      }

      game.precision_mode = false;
      
      // NOTE(ivan): user settings
      game.settings = {};
      game.settings.wheel_speed = 1;

      game.cursor = {
        x: 0, y: 0, 
        size: game.unit, 
        display_size: game.unit
      };

      game.src_changed = true;
      game.lnk_changed = true;

      edit_view.addEventListener("load", () => { game.src_changed = true; })
      link_view.addEventListener("load", () => { game.lnk_changed = true; })

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

        let precision = 1;
        
        if (game.precision_mode) {
          precision /= 10;
        }

        if (game.track_user) {
          game.cursor.size = clamp(
            game.unit * 5, 
            game.cursor.size + (event.deltaY / game.unit) * game.settings.wheel_speed * precision, 
            game.unit * 80
          );
        }
        if (event.deltaMode !== 0) {
          debugger;
        }
      })
      
      game.time = Date.now();
      game.angle = 0;
      game.user_is_on_tab = true;
      game.shapes = new Map();
      game.cursor.size = 100;
      game.cursor.down = false;
      game.cursor.hold_acc = game.unit / 10000;
      game.cursor.hold_velocity = 0;
      game.cursor.holding = 0;

      game.cursor.acc_rate = 0.05;
      game.cursor.zoom_velocity = 0;

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

      function loop() {
        ctx.clearRect(0, 0, spots_editor.width, spots_editor.height);

        if (game.src_changed) {
          game.src = edit_view.getAttribute("src");
          game.current_pair = game.selected_lnk + game.selected_src * game.pictures_count;
          game.src_changed = false;
        }
        if (game.lnk_changed) {
          game.lnk = link_view.getAttribute("src");
          game.current_pair = game.selected_lnk + game.selected_src * game.pictures_count;
          game.lnk_changed = false;
        }

        const CurrentShapeThresholds = {
          DO_NOTHING: 0,
          ADD: 1,
        };

        if (game.shape == CurrentShapeThresholds.ADD) { 
          const shapes = game.shapes.getOrInsertComputed(game.current_pair, () => []); 
          shapes.push({
            x:    game.cursor.holding_x, 
            y:    game.cursor.holding_y,
            size: game.cursor.holding_size
          });
        }
        

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
        
        const shapes = game.shapes.get(game.current_pair);
        if (shapes) {
          for (const shape of shapes) {
            draw_stamp(shape.x, shape.y, shape.size);
          }
        }

        ctx.save();
        {

          const now = Date.now();
          const dt = now - game.time;
          game.time = now; 
          
          // NOTE(ivan): this adds the illusion of smooth scroll between different browsers
          //             if you rely on browser's scroll input
          if (game.cursor.display_size < game.cursor.size) {
            game.cursor.zoom_velocity += game.cursor.acc_rate * dt
            game.cursor.display_size = clamp(game.cursor.display_size, game.cursor.display_size + game.cursor.zoom_velocity, game.cursor.size);
          } else if (game.cursor.display_size > game.cursor.size) {
            game.cursor.zoom_velocity += game.cursor.acc_rate * dt
            game.cursor.display_size = clamp(game.cursor.size, game.cursor.display_size - game.cursor.zoom_velocity, game.cursor.display_size);
          } else {
            game.cursor.zoom_velocity = 0;
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

          let progress = game.cursor.holding / (2 * Math.PI * game.cursor.display_size);
          
          
          ctx.translate(game.cursor.display_x, game.cursor.display_y);
          ctx.beginPath();
          let stamp_radius = game.cursor.display_size;
          if (game.cursor.down) {
            stamp_radius -= 4;
          }
          ctx.arc(0, 0, stamp_radius, 0, 2 *  Math.PI);
          ctx.fillStyle = "#facadeA0";
          ctx.fill();
          
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
