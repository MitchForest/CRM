import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon, Clock } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Input } from "@/components/ui/input"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

interface DateTimePickerProps {
  date?: Date
  onDateChange: (date: Date | undefined) => void
  placeholder?: string
  className?: string
  disabled?: boolean
}

export function DateTimePicker({ 
  date, 
  onDateChange, 
  placeholder = "Pick date and time", 
  className,
  disabled = false
}: DateTimePickerProps) {
  const [selectedDate, setSelectedDate] = React.useState<Date | undefined>(date)
  const [hours, setHours] = React.useState(date ? format(date, 'HH') : '09')
  const [minutes, setMinutes] = React.useState(date ? format(date, 'mm') : '00')

  React.useEffect(() => {
    if (selectedDate) {
      const newDate = new Date(selectedDate)
      newDate.setHours(parseInt(hours))
      newDate.setMinutes(parseInt(minutes))
      onDateChange(newDate)
    }
  }, [selectedDate, hours, minutes, onDateChange])

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "w-full justify-start text-left font-normal",
            !date && "text-muted-foreground",
            className
          )}
          disabled={disabled}
        >
          <CalendarIcon className="mr-2 h-4 w-4" />
          {date ? format(date, "PPP HH:mm") : <span>{placeholder}</span>}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <div className="p-3">
          <Calendar
            mode="single"
            selected={selectedDate}
            onSelect={setSelectedDate}
            initialFocus
          />
          <div className="mt-3 flex items-center gap-2 px-3">
            <Clock className="h-4 w-4 text-muted-foreground" />
            <Input
              type="number"
              min="0"
              max="23"
              value={hours}
              onChange={(e) => setHours(e.target.value.padStart(2, '0'))}
              className="w-16"
              placeholder="HH"
            />
            <span>:</span>
            <Input
              type="number"
              min="0"
              max="59"
              value={minutes}
              onChange={(e) => setMinutes(e.target.value.padStart(2, '0'))}
              className="w-16"
              placeholder="MM"
            />
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}