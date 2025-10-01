class Stack:
    def __init__(self):
        self.items = []

    def push(self, item):
        self.items.append(item)

    def pop(self):
        if not self.is_empty():
            return self.items.pop()
        else:
            return "Stack is empty"  

    def peek(self):
        if not self.is_empty():
            return self.items[-1]
        else:
            return "Stack is empty"   

    def is_empty(self):
        return len(self.items) == 0

    def size(self):
        return len(self.items)


# Test program
s = Stack()
print("Is stack empty?", s.is_empty())

s.push(1)
s.push(2)
s.push(3)
s.push(4)
s.push(5)
print("Size after push:", s.size())
print("Top element:", s.peek())

print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())

print("Is empty?", s.is_empty())
print("Pop from empty:", s.pop())
