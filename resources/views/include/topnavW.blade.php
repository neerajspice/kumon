<li class="dropdown">
    <a class="dropdown-toggle" data-toggle="dropdown" href="javascript:voide(0)">Inventory
     <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
        <li><a href="{{ url('stock') }}">Availble Stock : CI,NCI,Bags</a></li>
        <li><a href="{{ url('wksLevel') }}">Availble wks: Level Wise</a></li>
        <li><a href="{{ url('wks') }}">Availble Wks : Item Wise</a></li>
        <li><a href="{{ url('stockCenters') }}">Stock @ Centers</a></li>
        <li><a href="{{ url('consignments') }}">Consignments</a></li>
        <li><a href="{{ url('transfer') }}">Stock : Transfer @ Warehouses</a></li>
        <li><a href="{{ url('render/0') }}">Stock : Render to Center</a></li>
    </ul>
</li>


<!--li class="dropdown">
    <a class="dropdown-toggle" data-toggle="dropdown" href="javascript:voide(0)">Profile
     <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
        
        <li><a href="{{ url('/users/logs') }}">Logs</a></li>
    </ul>
</li-->