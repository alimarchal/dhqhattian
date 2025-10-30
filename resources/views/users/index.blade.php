<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight inline-block">
            Users
        </h2>
        <div class="flex justify-center items-center float-right">
            <a href="{{ route('users.create') }}" class="float-right ml-2 inline-flex items-center px-4 py-2 bg-red-800 border border-transparent
                        rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900
                        focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" \>
                Create User
            </a>



            <a href="javascript:;" id="toggle" class="float-right ml-1 inline-flex items-center px-4 py-2 bg-green-800 border border-transparent
                        rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900
                        focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" \>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>

            </a>
        </div>


    </x-slot>


    <div class="max-w-8xl mx-auto mt-6 px-4 sm:px-6 lg:px-8" style="display: none" id="filters">
        <div class="rounded-xl p-4 bg-white shadow-lg">
            <form action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="id" class="block text-gray-700 font-bold mb-2">Patient ID</label>
                        <input type="text" name="filter[id]" value="{{ request('filter.id') }}" id="id" class="w-full px-3 py-2 border rounded-md text-gray-700 focus:outline-none focus:border-blue-500" placeholder="Patient ID Type here">
                    </div>

                    <div>
                        <label for="first_name" class="block text-gray-700 font-bold mb-2">Name</label>
                        <input type="text" name="filter[first_name]" value="{{ request('filter.first_name') }}" id="first_name" class="w-full px-3 py-2 border rounded-md text-gray-700 focus:outline-none focus:border-blue-500" placeholder="Enter name">
                    </div>
                    <div>
                        <label for="father_husband_name" class="block text-gray-700 font-bold mb-2">Father/Son/Do</label>
                        <input type="text" name="filter[father_husband_name]" value="{{ request('filter.father_husband_name') }}" id="father_husband_name" class="w-full px-3 py-2 border rounded-md text-gray-700 focus:outline-none focus:border-blue-500" placeholder="Enter father/son/do">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Sex</label>
                        <div class="mt-2">
                            <label class="inline-flex items-center">
                                <input type="radio" name="filter[sex]" id="male" value="1" >
                                <span class="ml-2">Male</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="filter[sex]" id="female" value="0" >
                                <span class="ml-2">Female</span>
                            </label>

                        </div>
                    </div>
                    <div>
                        <label for="cnic" class="block text-gray-700 font-bold mb-2">CNIC</label>
                        <input type="text" name="filter[cnic]" id="cnic" value="{{ request('filter.cnic') }}" class="w-full px-3 py-2 border rounded-md text-gray-700 focus:outline-none focus:border-blue-500" placeholder="Enter CNIC (00000-0000000-0)">
                    </div>
                    <div>
                        <label for="mobile" class="block text-gray-700 font-bold mb-2">Mobile No.</label>
                        <input type="text" name="filter[mobile]" id="mobile_no" value="{{ request('filter.mobile') }}" class="w-full px-3 py-2 border rounded-md text-gray-700 focus:outline-none focus:border-blue-500"
                               placeholder="Enter mobile no. (0000-0000000)">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Government/Non-Government</label>
                        <div class="mt-2">
                            <label class="inline-flex items-center">
                                <input type="radio" class="form-radio" name="filter[government_non_gov]" value="1">
                                <span class="ml-2">Government</span>
                            </label>
                            <label class="inline-flex items-center ml-6">
                                <input type="radio" class="form-radio" name="filter[government_non_gov]" value="0">
                                <span class="ml-2">Non-Government</span>
                            </label>
                        </div>
                    </div>


                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                            Search
                        </button>
                    </div>


                </div>


            </form>
        </div>
    </div>

    <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden ">
                <div class="dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                    <!-- resources/views/users/create.blade.php -->


                    <table class="w-full text-sm border-collapse border border-slate-400 text-left text-black dark:text-gray-400">
                        <thead class="text-black uppercase bg-gray-50 dark:bg-gray-700 ">
                        <tr>
                            <th class="px-1 py-3 border border-black" >
                                ID
                            </th>
                            <th class="px-1 py-3 border border-black  text-center">
                                Name
                            </th>
                            <th class="px-1 py-3 border border-black  text-center">
                                Email
                            </th>
                            <th class="px-1 py-3 border border-black  text-center">
                                Role
                            </th>
                        </tr>
                        </thead>
                        <tbody>


                        @foreach ($users as $user)
                            <tr class="bg-white  border-b dark:bg-gray-800 dark:border-black text-left">
                                <th class="border px-2 py-2  border-black font-medium text-black dark:text-white">
                                    {{ $user->name }}
                                </th>
                                <th class="border px-2 py-2 border-black font-medium text-black dark:text-white text-center">
                                    {{ $user->name }}
                                </th>
                                <th class="border px-2 py-2 border-black font-medium text-black dark:text-white text-center">
                                    {{ $user->email }}
                                </th>
                                <th class="border px-2 py-2 border-black font-medium text-black dark:text-white text-center">
                                    @foreach ($user->roles as $role)
                                        {{ $role->name }}
                                        @if (!$loop->last)
                                            ,
                                        @endif
                                    @endforeach
                                </th>
                            </tr>
                        @endforeach

                        </tbody>
                    </table>



                </div>
            </div>
        </div>
    </div>
    @section('custom_script')
        <script>
            const targetDiv = document.getElementById("filters");
            const btn = document.getElementById("toggle");
            btn.onclick = function () {
                if (targetDiv.style.display !== "none") {
                    targetDiv.style.display = "none";
                } else {
                    targetDiv.style.display = "block";
                }
            };
        </script>
    @endsection
</x-app-layout>
