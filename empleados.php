<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="assets/js/sidebar.js" defer></script>
    <style>
    .navigation ul li:nth-child(8) {
        background-color: #fff;
    }

    .navigation ul li:nth-child(8) a {
        color: #001f47;
    }

    .navigation ul li:nth-child(8) a::before {
        content: "";
        position: absolute;
        right: 0;
        top: -50px;
        width: 50px;
        height: 50px;
        background-color: transparent;
        border-radius: 50%;
        box-shadow: 35px 35px 0 10px #fff;
        pointer-events: none;
    }

    .navigation ul li:nth-child(8) a::after {
        content: "";
        position: absolute;
        right: 0;
        bottom: -50px;
        width: 50px;
        height: 50px;
        background-color: transparent;
        border-radius: 50%;
        box-shadow: 35px -35px 0 10px #fff;
        pointer-events: none;
    }

    .navigation ul li:nth-child(8) a .icon img {
        content: url('assets/img/sidebar/theme-team.svg');
    }
    </style>

<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="toggle">
                <img src="assets/img/sidebar/dark-menu.svg">
            </div>

            <div class="search-group">
                <div class="search">
                    <label>
                        <input type="text" placeholder="Buscar aquí" id="search-input">
                        <img src="assets/img/sidebar/dark-search.svg">
                    </label>
                </div>

                <div class="filter">
                    <label>
                        <img src="assets/img/sidebar/dark-filter.svg">
                        <select id="filter-select">
                            <option value="1">Filtro</option>
                            <option value="2">Opción 2</option>
                        </select>
                    </label>
                </div>
            </div>

            <?php include_once 'includes/profile.php'; ?>
        </div>

</body>

</html>