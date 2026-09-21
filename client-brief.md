So, basically, we've been operating the agency for several years now, and the way we're working is starting to become a problem.

We have around 40–50 vehicles. Most are normal passenger cars: small economical cars, sedans, SUVs, and a few utility vehicles. We mainly rent to individuals, although occasionally we work with companies that need vehicles for employees for several days or weeks.

Right now, most of our work is manual.

When somebody comes to rent a car, the employee takes copies of their documents, fills out the rental contract, checks which vehicles are available, records the payment, and then someone updates an Excel file.

We have several Excel files actually.

One for vehicles, another for customers, another for rentals and payments, and one that we use to track maintenance.

And that's part of the problem.

Sometimes the information doesn't match.

For example, Excel might say a vehicle is available while another employee already promised it to a customer for tomorrow.

We also have WhatsApp messages between employees saying things like:

"Clio 2022 reserved from Sunday until Wednesday."

Obviously that's not a good system.

What I want is one application where the agency can manage the entire rental operation.

The most important thing for me is knowing the situation of my fleet.

When I open the application, I want to immediately understand what's happening.

How many vehicles do we have?

How many are currently rented?

How many are available?

Which vehicles have reservations coming soon?

Which vehicles are in maintenance?

And maybe which ones have documents that are about to expire.

For every vehicle, we obviously need basic information such as registration number, make, model, year, fuel type, transmission, mileage, daily rental price, and things like that.

I'd also like to keep some documents associated with the vehicle.

For example:

insurance
technical inspection
registration documents
maybe maintenance documents

I don't necessarily need some complicated document-management system. Just the important information and perhaps uploaded scans.

Then we have the customers.

Today we basically create a customer folder physically.

We take their information and copies of documents.

Usually we need their name, phone number, address, ID information, driver's licence information, date of birth, things like that.

I'd like the application to keep the customer's history as well.

So if Mohamed rents from us five times, I shouldn't have five different customer records. When I open Mohamed's profile, I should see his previous rentals.

It would also be useful to know if we've had problems with a customer before.

For example:

returned a vehicle late
unpaid amount
accident
damaged vehicle
some other important note

I'm not saying we need some complicated customer scoring algorithm. Just somewhere employees can record important information.

The core of the application is obviously reservations and rentals.

This is where I really want the system to improve our work.

Let's say somebody calls today and says:

"I need an automatic Clio from the 20th until the 24th."

The employee should be able to check the system and see what's available during those dates.

This is very important.

I don't just want the system to show vehicles that are available right now. It has to understand reservations.

For example:

Vehicle A might be sitting in our parking lot today, but if somebody has already reserved it starting tomorrow, the system needs to know that.

And obviously we cannot reserve the same vehicle for two customers during overlapping periods.

Sometimes the customer reserves a category rather than a specific vehicle, though.

For example, they might just ask for an economical automatic car.

I'm not completely sure how I want you to handle that yet. We can discuss it.

Once the customer actually takes the vehicle, we create the rental.

At that point I need the system to record things like:

customer, vehicle, rental start date, expected return date, rental price, deposit, payment, starting mileage, fuel level and maybe the condition of the vehicle.

We normally inspect the car before handing it over.

I'd like the employee to record the vehicle condition.

Nothing extremely complicated. Maybe notes and some photos.

For example:

Before rental:

Front bumper scratch already exists.

Rear-right wheel slightly damaged.

Fuel: 75%.

Mileage: 64,230 km.

Then when the vehicle comes back, we inspect it again.

We record:

return mileage, fuel level, actual return time, new damage if there is any, and additional charges.

Payments are another area I want cleaned up.

Customers don't always pay everything at once.

Sometimes they pay an advance when reserving.

Then they pay the remaining amount when taking the vehicle.

Sometimes there's a security deposit.

And sometimes there are extra charges when the vehicle is returned.

For example:

late-return charges, missing fuel, damage, or additional rental days.

So I need to be able to see clearly:

Total rental amount → amount paid → remaining balance.

We normally accept cash and sometimes other payment methods, so payment method should probably be recorded.

I'd also like employees to be able to print a receipt.

I'm not asking for full accounting software, though.

I already have accounting procedures outside this system.

This application should manage rental payments, not become an ERP.

Maintenance is also important.

Right now we track oil changes and repairs manually.

For each vehicle I want to see its maintenance history.

For example:

Renault Clio — 123456-116-16

Oil change
12/08/2026
61,200 km
Cost: 9,000 DA

Brake pads
03/09/2026
64,000 km
Cost: 18,500 DA

Something like that.

And preferably we can specify when the next service should happen.

For example:

Next oil change at 71,000 km.

It would be helpful if the dashboard warned us when a vehicle is approaching maintenance.

Vehicles undergoing maintenance obviously shouldn't appear as available for rental.

I also need basic expense tracking.

Nothing comparable to accounting software.

But I'd like to record expenses related to vehicles.

Maintenance, spare parts, cleaning, towing, maybe insurance costs.

Eventually I want to be able to answer questions such as:

"How much money did this vehicle cost us during the last six months?"

and compare that with:

"How much rental revenue did this vehicle generate?"

That would help me understand whether keeping a vehicle is profitable.

Employees will use the system too.

Right now let's assume we have:

Me — the manager

I should basically have access to everything.

Rental agents

They manage customers, reservations, rentals, vehicle handovers and returns.

Someone handling finance/cashier duties

They mainly need access to payments and receipts.

I don't think we need an extremely complex permissions system.

Maybe roles and permissions would be enough.

But I definitely want to know who performed important actions.

If somebody changes a rental price from 8,000 DA/day to 6,000 DA/day, I want to know who changed it.

Same thing if somebody cancels a reservation or deletes a payment.

Reports would be useful too.

Again, don't turn this into some business-intelligence project.

I mainly want useful operational reports.

Things like:

revenue this month, number of rentals, most rented vehicles, vehicle utilization, outstanding customer balances, upcoming reservations, vehicles currently overdue, maintenance costs, and maybe revenue/expenses per vehicle.

And I want to be able to filter things by dates.

There is one other thing that's important.

Late returns.

If a vehicle was supposed to be returned yesterday and hasn't been returned, I want that to stand out immediately.

Something like:

OVERDUE — Peugeot 208 — expected 15/09/2026 at 18:00 — Customer: Ahmed B.

Because an overdue vehicle can affect the next reservation.

There are things I don't need right now.

I don't want customers creating accounts.

I don't need a mobile application.

I don't need customers booking online.

I don't need GPS tracking.

I don't need automatic payment gateways.

I don't need integration with government systems.

I don't need advanced accounting or payroll.

Those things could theoretically come later, but for the first version we're building an internal management platform for the agency.

What I really want is to replace:

paper folders + Excel sheets + WhatsApp + employees remembering things

with one reliable system.

If I arrive at the office in the morning, I should be able to open the dashboard and understand the state of the company in about 30 seconds.

That's roughly what I have in mind.
