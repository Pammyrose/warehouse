<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="/dist/tailwind.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css"
    />
  </head>
  <style>
    .content-wrapper {
    position: relative;
    z-index: 100;
}


.color{
  background: #3498db;

}
  </style>
  <body class=" content-wrapper">
    <span
      class="absolute text-white text-4xl top-5 left-4 cursor-pointer"
      onclick="openSidebar()"
    >
      <i class="bi bi-filter-left px-2 bg-gray-900 rounded-md"></i>
    </span>
    <div
      class="color sidebar fixed top-0 bottom-0 lg:left-0 p-2 w-[300px] overflow-y-auto text-center bg-gray-900"
    >
      <div class="text-gray-100 text-xl">
        <div class="p-2.5 mt-1 flex items-center">
         
          <h1 class="font-bold text-gray-200 text-[15px] ml-3">Warehouse Inventory</h1>
          <i
            class="bi bi-x cursor-pointer ml-28 lg:hidden"
            onclick="openSidebar()"
          ></i>
        </div>
        <div class="my-2 bg-gray-600 h-[1px]"></div>
      </div>

      <a href="dashboard.php"><div
        class="p-2.5 mt-3 flex items-center rounded-md px-4 duration-300 cursor-pointer hover:bg-gray-100 hover:text-black text-white"
      >
        <i class="bi bi-house-door-fill"></i>
        
        <span class="text-[15px] ml-4  font-bold">Dashboard</span>
      </div></a>
      <a href="supplier.php"><div
        class="p-2.5 mt-3 flex items-center rounded-md px-4 duration-300 cursor-pointer hover:bg-gray-100 hover:text-black text-white"
      >
      <i class="bi bi-box2-fill"></i>
      <span class="text-[15px] ml-4  font-bold">Supplier</span>
      </div>
      </a>
      <div class="my-4 bg-gray-600 h-[1px]"></div>
      <div
        class="p-2.5 mt-3 flex items-center rounded-md px-4 duration-300 cursor-pointer hover:bg-gray-100 hover:text-black text-white"
        onclick="dropdown()"
      >
      <i class="bi bi-cart4"></i>
        <div class="flex justify-between w-full items-center ">
          <span class="text-[15px] ml-4 font-bold">Inventory</span>
          <span class="text-sm rotate-180" id="arrow">
            <i class="bi bi-chevron-down"></i>
          </span>
        </div>
      </div>
      <div
        class="text-left text-sm mt-2 w-4/5 mx-auto text-gray-200 font-bold "
        id="submenu"
      >
      <a href="product.php">
        <h1 class="cursor-pointer p-2  rounded-md mt-1 hover:bg-gray-100 hover:text-black">
         Products
        </h1>
        </a>
        <h1 class="cursor-pointer p-2  rounded-md mt-1 hover:bg-gray-100 hover:text-black">
          Stock
        </h1>

      </div>
      <a href="user.php"><div
        class="p-2.5 mt-3 flex items-center rounded-md px-4 duration-300 cursor-pointer hover:bg-gray-100 hover:text-black text-white"
      >
      <i class="bi bi-person"></i>
      <span class="text-[15px] ml-4  font-bold">Users</span>
      </div>
      </a>
      <div
        class="p-2.5 mt-3 flex items-center rounded-md px-4 duration-300 cursor-pointer hover:bg-gray-100 hover:text-black text-white"
      >
        <i class="bi bi-box-arrow-in-right"></i>
        <span class="text-[15px] ml-4  font-bold">Logout</span>
      </div>
    </div>

    <script type="text/javascript">
      function dropdown() {
        document.querySelector("#submenu").classList.toggle("hidden");
        document.querySelector("#arrow").classList.toggle("rotate-0");
      }
      dropdown();

      function openSidebar() {
        document.querySelector(".sidebar").classList.toggle("hidden");
      }
    </script>
  </body>
</html>